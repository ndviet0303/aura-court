<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\LegacyBookingMapping;
use App\Models\LegacyBookingSlotMapping;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerRefundObligation;
use App\Models\PriceQuote;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\OwnerRefundService;
use App\Services\Payment\ReservationPaymentService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Policy-governed atomic reschedule (booking change) for direct-owner reservations.
 *
 * A change is priced against the accepted change policy snapshot. When the new
 * court-time costs more, a replacement is held and a `booking_change` payment
 * attempt collects the verified difference; the original stays protected until
 * the receipt is verified and the swap commits (or the hold/attempt expires,
 * preserving the original). When the new court-time costs the same or less, the
 * swap is applied immediately and only the legitimate refund difference (if any)
 * is recorded as an owner refund obligation.
 */
class RescheduleService
{
    public function __construct(
        private readonly PriceQuoteService $quotes,
        private readonly OwnerPaymentAttemptService $attempts,
        private readonly OwnerRefundService $refunds,
        private readonly SlotSelectionPolicy $slotPolicy,
    ) {}

    /**
     * Server preview of a proposed change: deadline, fee, extra/refund amount and
     * replacement availability. Pricing the new slots also asserts availability
     * (409 when the replacement court-time is occupied/blocked).
     */
    public function preview(Reservation $original, User $actor, int $courtId, array $slots): array
    {
        $locked = Reservation::query()->withoutGlobalScope('tenant_isolation')->whereKey($original->id)->firstOrFail();
        $this->assertReschedulable($locked);
        [$change, $deadline] = $this->changeTerms($locked);
        $this->assertBeforeDeadline($deadline);

        $quote = $this->quotes->create((int) $locked->user_id, (int) $locked->organization_id, $courtId, $slots);
        $newTotal = (int) $quote->subtotal;
        $originalPaid = $this->originalPaidAmount($locked);
        $changeFee = $this->changeFee($change, $newTotal);
        $net = $newTotal + $changeFee - $originalPaid;

        return [
            'reservation_code' => $locked->code,
            'new_court_id' => (int) $quote->court_id,
            'slots' => collect($quote->slot_snapshot)
                ->map(fn (array $slot) => [
                    'starts_at' => $slot['starts_at'],
                    'ends_at' => $slot['ends_at'],
                    'unit_price' => (int) $slot['unit_price'],
                ])->values(),
            'policy_version' => data_get($locked->metadata, 'booking_policy_snapshot.version'),
            'deadline_at' => $deadline->toIso8601String(),
            'original_paid_amount' => $originalPaid,
            'new_total_amount' => $newTotal,
            'change_fee_amount' => $changeFee,
            'extra_payment_amount' => max(0, $net),
            'refund_amount' => max(0, -$net),
            'currency' => $locked->currency,
        ];
    }

    /**
     * Request a change. Secures the replacement inventory, then either collects a
     * verified difference (more expensive) or swaps atomically and records the
     * legitimate refund difference (cheaper/equal). Idempotent per idempotency key.
     *
     * @param  array{bank_code?:string, account_name?:string, account_number?:string}  $recipient
     */
    public function request(Reservation $original, User $actor, int $courtId, array $slots, string $idempotencyKey, array $recipient = []): array
    {
        try {
            return DB::transaction(function () use ($original, $courtId, $slots, $idempotencyKey, $recipient): array {
                $locked = Reservation::query()->withoutGlobalScope('tenant_isolation')->whereKey($original->id)->lockForUpdate()->firstOrFail();
                $payer = User::query()->findOrFail((int) $locked->user_id);

                // Idempotent replay: same key already produced a replacement.
                $existing = Reservation::query()->withoutGlobalScope('tenant_isolation')
                    ->where('organization_id', $locked->organization_id)
                    ->where('metadata->reschedule_of', $locked->code)
                    ->where('metadata->reschedule_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $this->serializeRequest($locked->fresh(), $existing->fresh(), $this->pendingAttempt($existing), $this->refundFor($existing));
                }

                $this->assertReschedulable($locked);

                // Only one in-flight change per reservation.
                $pending = Reservation::query()->withoutGlobalScope('tenant_isolation')
                    ->where('organization_id', $locked->organization_id)
                    ->where('metadata->reschedule_of', $locked->code)
                    ->where('status', 'held')
                    ->exists();
                abort_if($pending, 409, 'A reschedule request is already pending for this reservation.');

                [$change, $deadline] = $this->changeTerms($locked);
                $this->assertBeforeDeadline($deadline);

                $quote = $this->quotes->create((int) $locked->user_id, (int) $locked->organization_id, $courtId, $slots);
                $quote = PriceQuote::query()->withoutGlobalScope('tenant_isolation')->whereKey($quote->id)->lockForUpdate()->firstOrFail();
                abort_if($quote->status !== 'open', 409, 'Price quote has already been accepted.');

                $newTotal = (int) $quote->subtotal;
                $originalPaid = $this->originalPaidAmount($locked);
                $changeFee = $this->changeFee($change, $newTotal);
                $net = $newTotal + $changeFee - $originalPaid;
                $extra = max(0, $net);
                $refundDue = max(0, -$net);

                $court = Court::query()->whereKey($quote->court_id)->with('venue')->lockForUpdate()->firstOrFail();
                $venue = Venue::query()->whereKey($court->venue_id)->lockForUpdate()->firstOrFail();
                ['snapshot' => $policySnapshot, 'expires_at' => $holdExpiresAt] = $this->captureHold($venue);

                $excludeBookingSlotIds = LegacyBookingSlotMapping::query()
                    ->withoutGlobalScope('tenant_isolation')
                    ->whereIn('reservation_slot_id', $locked->slots()->pluck('id'))
                    ->pluck('legacy_booking_slot_id')
                    ->all();
                $this->slotPolicy->assertAllowed(
                    $court,
                    collect($quote->slot_snapshot)->map(fn (array $slotSnapshot): array => [
                        'start' => $slotSnapshot['starts_at'],
                        'end' => $slotSnapshot['ends_at'],
                    ])->all(),
                    excludeReservationId: (int) $locked->id,
                    excludeBookingSlotIds: $excludeBookingSlotIds,
                );

                $changeSnapshot = [
                    'reschedule_of' => $locked->code,
                    'reschedule_key' => $idempotencyKey,
                    'change' => [
                        'new_total' => $newTotal,
                        'original_paid' => $originalPaid,
                        'change_fee' => $changeFee,
                        'net' => $net,
                        'extra_payment' => $extra,
                        'refund_amount' => $refundDue,
                        'policy' => $change,
                    ],
                ];

                $replacement = $this->createReplacement($locked, $quote, $policySnapshot, $extra > 0 ? $holdExpiresAt : null, $changeSnapshot);

                if ($extra > 0) {
                    // More expensive: collect the verified difference before committing the swap.
                    $connectionId = $venue->current_receiving_connection_id;
                    abort_unless($connectionId, 409, 'This venue has no selected receiving account.');
                    $connection = ReceivingConnection::query()->findOrFail($connectionId);
                    $reference = 'AC'.Str::upper(Str::random(18));
                    $attempt = $this->attempts->createAttempt(
                        $connection,
                        $payer,
                        'booking_change',
                        $replacement->code,
                        $reference,
                        $extra,
                        strtoupper((string) $locked->currency),
                        Carbon::parse($holdExpiresAt),
                        'reschedule:'.$replacement->id.':'.$reference,
                    );

                    return $this->serializeRequest($locked->fresh(), $replacement->fresh(), $attempt, null);
                }

                // Cheaper or equal: swap atomically now and record only the legitimate refund difference.
                $this->applySwap($locked, $replacement);
                $obligation = null;
                if ($refundDue > 0) {
                    $obligation = $this->refunds->requestForReschedule($locked->fresh(), $payer, $refundDue, $recipient, $idempotencyKey, $replacement->code);
                }

                return $this->serializeRequest($locked->fresh(), $replacement->fresh(), null, $obligation);
            });
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'reservation_entitlements_active_unique')
                || (str_contains($message, 'reservation_entitlements') && str_contains($message, 'unique'))) {
                abort(409, 'The selected court-time is no longer available.');
            }
            throw $exception;
        }
    }

    /**
     * Commit the swap once a `booking_change` receipt is verified. Called from the
     * receipt processor inside its transaction; both reservations are locked here.
     */
    public function commitSwap(Reservation $replacement): void
    {
        $originalCode = data_get($replacement->metadata, 'reschedule_of');
        abort_unless(is_string($originalCode) && $originalCode !== '', 409, 'Replacement reservation is not linked to an original booking.');

        $original = Reservation::query()->withoutGlobalScope('tenant_isolation')
            ->where('organization_id', $replacement->organization_id)
            ->where('code', $originalCode)
            ->lockForUpdate()
            ->firstOrFail();

        $this->applySwap($original, $replacement);
    }

    /**
     * Release the original inventory once and activate the replacement. Idempotent:
     * a re-run over an already-cancelled original / confirmed replacement is a no-op.
     */
    public function applySwap(Reservation $original, Reservation $replacement): void
    {
        $now = now();

        if (in_array($original->status, ['confirmed', 'in_use', 'held'], true)) {
            $metadata = (array) ($original->metadata ?? []);
            $metadata['rescheduled_to'] = $replacement->code;
            $metadata['rescheduled_at'] = $now->toIso8601String();
            $original->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => $now,
                'metadata' => $metadata,
            ])->save();
            $original->slots()->whereIn('status', ['held', 'confirmed'])->update(['status' => 'cancelled']);
            $original->entitlements()->whereIn('status', ['held', 'active'])->update(['status' => 'cancelled']);
            $this->cancelLegacyProjection($original, $replacement->code, $now);
        }

        if ($replacement->status !== 'confirmed') {
            $replacement->forceFill(['status' => 'confirmed', 'confirmed_at' => $now, 'hold_expires_at' => null])->save();
        }
        $replacement->slots()->where('status', 'held')->update(['status' => 'confirmed']);
        $replacement->entitlements()->where('status', 'held')->update(['status' => 'active']);
    }

    private function assertReschedulable(Reservation $reservation): void
    {
        abort_unless($reservation->payment_model === 'direct_owner_v1', 409, 'Rescheduling is available only for direct-owner reservations.');
        abort_unless(in_array($reservation->status, ['confirmed', 'in_use'], true), 409, 'Only a confirmed reservation can be rescheduled.');
    }

    /**
     * Resolve the accepted change policy and its deadline. Missing/insufficient
     * change terms are surfaced as a required explicit resolution, never a
     * fabricated default (legacy-policy scenario).
     *
     * @return array{0: array<string,mixed>, 1: Carbon}
     */
    private function changeTerms(Reservation $reservation): array
    {
        $policySnapshot = data_get($reservation->metadata, 'booking_policy_snapshot');
        if (! is_array($policySnapshot)) {
            $legacyMapping = $reservation->legacyMapping()->first();
            $booking = $legacyMapping ? Booking::query()->find($legacyMapping->legacy_booking_id) : null;
            $policySnapshot = data_get($booking?->policy_snapshot, 'venue_booking_policy');
        }
        $change = data_get($policySnapshot, 'settings.change');
        abort_unless(
            is_array($change),
            409,
            'The accepted change policy snapshot is unavailable for this reservation; an explicit supported resolution is required.'
        );

        $feeType = (string) ($change['fee_type'] ?? '');
        abort_unless(in_array($feeType, ['none', 'fixed', 'percentage'], true) && (int) ($change['fee_value'] ?? -1) >= 0, 409, 'The captured change fee policy is invalid.');
        abort_if($feeType === 'percentage' && (int) $change['fee_value'] > 100, 409, 'The captured change percentage is invalid.');

        $earliestStart = $reservation->slots()->whereIn('status', ['confirmed', 'in_use'])->min('starts_at');
        abort_unless($earliestStart, 409, 'The reservation has no active slots to reschedule.');
        $deadline = Carbon::parse($earliestStart)->subHours((int) ($change['deadline_hours'] ?? 0));

        return [$change, $deadline];
    }

    private function assertBeforeDeadline(Carbon $deadline): void
    {
        abort_if(now()->greaterThan($deadline), 422, 'The captured change deadline has passed.');
    }

    private function changeFee(array $change, int $newTotal): int
    {
        return match ((string) $change['fee_type']) {
            'none' => 0,
            'fixed' => min($newTotal, (int) $change['fee_value']),
            'percentage' => intdiv($newTotal * min(100, max(0, (int) $change['fee_value'])), 100),
            default => abort(409, 'The captured change fee policy is invalid.'),
        };
    }

    private function originalPaidAmount(Reservation $reservation): int
    {
        $attemptIds = OwnerPaymentAttempt::query()
            ->where('organization_id', $reservation->organization_id)
            ->whereIn('purpose', ['booking', 'split_share', 'booking_change'])
            ->where('purpose_id', $reservation->code)
            ->where('status', OwnerPaymentAttempt::STATUS_PAID)
            ->pluck('id');

        return (int) OwnerPaymentAllocation::query()->whereIn('payment_attempt_id', $attemptIds)->sum('amount');
    }

    /**
     * @return array{snapshot: array<string,mixed>, expires_at: string}
     */
    private function captureHold(Venue $venue): array
    {
        $policy = $venue->currentBookingPolicy()->first();
        abort_unless($policy instanceof VenueBookingPolicy, 409, 'A captured venue booking policy is required for direct payment.');
        $settings = (array) $policy->settings;
        $holdMinutes = (int) data_get($settings, 'booking.hold_ttl_minutes', 0);
        abort_unless($holdMinutes >= 1 && $holdMinutes <= 1440, 409, 'The venue payment hold duration is invalid.');

        return [
            'snapshot' => [
                'version' => (int) $policy->version,
                'captured_at' => now()->toIso8601String(),
                'settings' => $settings,
            ],
            'expires_at' => now()->addMinutes($holdMinutes)->toIso8601String(),
        ];
    }

    private function createReplacement(
        Reservation $original,
        PriceQuote $quote,
        array $policySnapshot,
        ?string $holdExpiresAt,
        array $changeSnapshot
    ): Reservation {
        $metadata = $changeSnapshot;
        $metadata['booking_policy_snapshot'] = $policySnapshot;
        if ($quote->benefit_snapshot) {
            $metadata['benefit_snapshot'] = $quote->benefit_snapshot;
        }

        $replacement = Reservation::create([
            'organization_id' => $quote->organization_id,
            'user_id' => $original->user_id,
            'court_id' => $quote->court_id,
            'price_quote_id' => $quote->id,
            'code' => $this->uniqueCode(),
            'status' => 'held',
            'currency' => $quote->currency,
            'total_amount' => (int) $quote->subtotal,
            'hold_expires_at' => $holdExpiresAt,
            'metadata' => $metadata,
            'payment_model' => 'direct_owner_v1',
        ]);

        foreach ($quote->slot_snapshot as $slotSnapshot) {
            $slot = ReservationSlot::create([
                'organization_id' => $quote->organization_id,
                'reservation_id' => $replacement->id,
                'court_id' => $quote->court_id,
                'starts_at' => $slotSnapshot['starts_at'],
                'ends_at' => $slotSnapshot['ends_at'],
                'unit_price' => $slotSnapshot['unit_price'],
                'status' => 'held',
                'snapshot' => $slotSnapshot,
            ]);

            ReservationEntitlement::create([
                'organization_id' => $quote->organization_id,
                'reservation_id' => $replacement->id,
                'reservation_slot_id' => $slot->id,
                'user_id' => $original->user_id,
                'court_id' => $quote->court_id,
                'starts_at' => $slot->starts_at,
                'ends_at' => $slot->ends_at,
                'amount' => $slot->unit_price,
                'status' => 'held',
            ]);
        }

        $quote->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'accepted_snapshot' => [
                'quote_token' => $quote->quote_token,
                'rule_version' => $quote->rule_version,
                'slot_snapshot' => $quote->slot_snapshot,
                'subtotal' => $quote->subtotal,
                'total_amount' => $quote->total_amount,
                'benefit_snapshot' => $quote->benefit_snapshot,
            ],
        ]);

        return $replacement->load(['court.venue', 'slots', 'entitlements']);
    }

    private function cancelLegacyProjection(Reservation $original, string $replacementCode, Carbon $now): void
    {
        $mapping = LegacyBookingMapping::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('reservation_id', $original->id)
            ->first();
        if (! $mapping) {
            return;
        }

        $booking = Booking::query()->whereKey($mapping->legacy_booking_id)->lockForUpdate()->first();
        if (! $booking) {
            return;
        }

        $legacySlotIds = LegacyBookingSlotMapping::query()
            ->withoutGlobalScope('tenant_isolation')
            ->whereIn('reservation_slot_id', $original->slots()->pluck('id'))
            ->pluck('legacy_booking_slot_id');
        BookingSlot::query()->whereIn('id', $legacySlotIds)->update(['status' => 'cancelled']);

        if (! $booking->slots()->whereIn('status', ['held', 'confirmed'])->exists()) {
            $booking->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => $now,
                'cancellation_reason' => 'rescheduled_to:'.$replacementCode,
            ])->save();
        }

        app(LegacyBookingBackfillService::class)->syncProjection($booking->fresh());
    }

    private function pendingAttempt(Reservation $replacement): ?OwnerPaymentAttempt
    {
        return OwnerPaymentAttempt::query()
            ->where('organization_id', $replacement->organization_id)
            ->where('purpose', 'booking_change')
            ->where('purpose_id', $replacement->code)
            ->latest('id')
            ->first();
    }

    private function refundFor(Reservation $replacement): ?OwnerRefundObligation
    {
        $originalCode = data_get($replacement->metadata, 'reschedule_of');
        if (! is_string($originalCode) || $originalCode === '') {
            return null;
        }
        $original = Reservation::query()->withoutGlobalScope('tenant_isolation')
            ->where('organization_id', $replacement->organization_id)
            ->where('code', $originalCode)
            ->first();

        return $original
            ? OwnerRefundObligation::query()
                ->where('reservation_id', $original->id)
                ->where('reason', 'reschedule_difference:'.$replacement->code)
                ->latest('id')
                ->first()
            : null;
    }

    private function serializeRequest(
        Reservation $original,
        Reservation $replacement,
        ?OwnerPaymentAttempt $attempt,
        ?OwnerRefundObligation $refund
    ): array {
        return [
            'reservation_code' => $original->code,
            'status' => $original->status,
            'replacement' => [
                'code' => $replacement->code,
                'status' => $replacement->status,
                'court_id' => (int) $replacement->court_id,
                'total_amount' => (int) $replacement->total_amount,
                'hold_expires_at' => $replacement->hold_expires_at?->toIso8601String(),
                'slots' => $replacement->slots()->orderBy('starts_at')->get(['starts_at', 'ends_at', 'unit_price']),
            ],
            'change' => data_get($replacement->metadata, 'change'),
            'payment_attempt' => $attempt ? app(ReservationPaymentService::class)->instructions($attempt) : null,
            'refund' => $refund ? $this->refunds->serialize($refund->load(['reservation', 'allocations.paymentAllocation', 'evidence'])) : null,
        ];
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'RSV'.strtoupper(Str::random(13));
        } while (Reservation::query()->where('code', $code)->exists());

        return $code;
    }
}
