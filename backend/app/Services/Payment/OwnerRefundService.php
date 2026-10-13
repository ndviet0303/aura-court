<?php

namespace App\Services\Payment;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\LegacyBookingMapping;
use App\Models\LegacyBookingSlotMapping;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerRefundAllocation;
use App\Models\OwnerRefundEvidence;
use App\Models\OwnerRefundObligation;
use App\Models\PointLedger;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\DiscountService;
use App\Services\Finance\FinancialPostingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OwnerRefundService
{
    public function __construct(private readonly FinancialPostingService $financials) {}

    public function quote(Reservation $reservation, User $payer, array $slotIds): array
    {
        $locked = Reservation::query()->withoutGlobalScope('tenant_isolation')->findOrFail($reservation->id);
        abort_unless((int) $locked->user_id === (int) $payer->id, 404);
        $this->assertSlotsNotAlreadyRequested($locked, array_map('intval', $slotIds));
        $calculation = $this->calculate($locked, $slotIds);

        return [
            'reservation_code' => $locked->code,
            'slot_ids' => collect($slotIds)->map(fn ($id) => (int) $id)->unique()->sort()->values(),
            'calculation' => $calculation['public_calculation'],
            'points_to_restore' => $calculation['points_to_restore'],
        ];
    }

    public function request(
        Reservation $reservation,
        User $payer,
        array $slotIds,
        string $reason,
        array $recipient,
        string $idempotencyKey
    ): OwnerRefundObligation {
        return DB::transaction(function () use ($reservation, $payer, $slotIds, $reason, $recipient, $idempotencyKey): OwnerRefundObligation {
            $locked = Reservation::query()->withoutGlobalScope('tenant_isolation')->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === (int) $payer->id, 404);
            abort_unless($locked->payment_model === 'direct_owner_v1', 409, 'Owner refunds are available only for direct-owner reservations.');

            $slotIds = collect($slotIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $requestFingerprint = hash('sha256', json_encode([
                'reservation_id' => $locked->id,
                'slot_ids' => $slotIds,
                'reason' => trim($reason),
                'recipient' => $recipient,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $existing = OwnerRefundObligation::query()
                ->where('organization_id', $locked->organization_id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_fingerprint, $requestFingerprint), 409, 'Idempotency key was already used for a different refund request.');

                return $existing->load(['allocations.paymentAllocation', 'evidence']);
            }

            $calculation = $this->calculate($locked, $slotIds);
            abort_if((int) $calculation['refund_amount'] > 0 && $recipient === [], 422, 'A recipient bank account is required for a cash refund.');
            $this->assertSlotsNotAlreadyRequested($locked, $slotIds);

            $attempt = $calculation['attempt'];
            $policySnapshot = $calculation['policy_snapshot'];
            $refund = OwnerRefundObligation::create([
                'organization_id' => $locked->organization_id,
                'venue_id' => $locked->court()->value('venue_id'),
                'reservation_id' => $locked->id,
                'booking_id' => LegacyBookingMapping::query()
                    ->withoutGlobalScope('tenant_isolation')
                    ->where('reservation_id', $locked->id)
                    ->value('legacy_booking_id'),
                'payer_id' => $payer->id,
                'payment_attempt_id' => $attempt?->id ?? $calculation['attempt_id'],
                'reference_code' => 'RF'.Str::upper(Str::random(18)),
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $requestFingerprint,
                'slot_ids' => $slotIds,
                'gross_amount' => $calculation['gross_amount'],
                'fee_amount' => $calculation['fee_amount'],
                'amount' => $calculation['refund_amount'],
                'currency' => $locked->currency,
                'status' => OwnerRefundObligation::STATUS_REQUESTED,
                'reason' => trim($reason),
                'policy_snapshot' => $policySnapshot,
                'recipient_ciphertext' => Crypt::encryptString(json_encode($recipient, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                'due_at' => $calculation['due_at'],
                'requested_at' => now(),
                'requested_by' => $payer->id,
            ]);

            foreach ($calculation['allocation_shares'] as $share) {
                if ($share['gross_amount'] <= 0) {
                    continue;
                }
                OwnerRefundAllocation::create([
                    'owner_refund_obligation_id' => $refund->id,
                    'owner_payment_allocation_id' => $share['allocation_id'],
                    'gross_amount' => $share['gross_amount'],
                    'fee_amount' => $share['fee_amount'],
                    'amount' => $share['amount'],
                    'created_at' => now(),
                ]);
            }

            $this->recordEvidence($refund, 'requested', $payer->id, [
                'slot_ids' => $slotIds,
                'reason' => trim($reason),
                'recipient' => $recipient,
                'calculation' => $calculation['public_calculation'],
            ]);

            return $refund->load(['allocations.paymentAllocation', 'evidence']);
        });
    }

    /**
     * Record only the legitimate refund difference produced by a cheaper/equal
     * reschedule swap. Unlike {@see request()} this is not slot-based cancellation:
     * the amount is the already-computed price difference, allocated back across the
     * original payer receipts and routed through the same owner review/acknowledge
     * lifecycle (no automated bank debit).
     *
     * @param  array{bank_code?:string, account_name?:string, account_number?:string}  $recipient
     */
    public function requestForReschedule(
        Reservation $reservation,
        User $payer,
        int $refundAmount,
        array $recipient,
        string $idempotencyKey,
        string $replacementCode
    ): OwnerRefundObligation {
        return DB::transaction(function () use ($reservation, $payer, $refundAmount, $recipient, $idempotencyKey, $replacementCode): OwnerRefundObligation {
            $locked = Reservation::query()->withoutGlobalScope('tenant_isolation')->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->user_id === (int) $payer->id, 404);
            abort_unless($locked->payment_model === 'direct_owner_v1', 409, 'Owner refunds are available only for direct-owner reservations.');
            abort_unless($refundAmount > 0, 422, 'A reschedule refund difference must be greater than zero.');

            $requestFingerprint = hash('sha256', json_encode([
                'reservation_id' => $locked->id,
                'reschedule_to' => $replacementCode,
                'refund_amount' => $refundAmount,
                'recipient' => $recipient,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $existing = OwnerRefundObligation::query()
                ->where('organization_id', $locked->organization_id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_fingerprint, $requestFingerprint), 409, 'Idempotency key was already used for a different refund request.');

                return $existing->load(['allocations.paymentAllocation', 'evidence']);
            }

            abort_if($recipient === [], 422, 'A recipient bank account is required for a cash refund difference.');

            $attempts = OwnerPaymentAttempt::query()
                ->where('organization_id', $locked->organization_id)
                ->whereIn('purpose', ['booking', 'split_share', 'booking_change'])
                ->where('purpose_id', $locked->code)
                ->where('status', OwnerPaymentAttempt::STATUS_PAID)
                ->get();
            $allocations = OwnerPaymentAllocation::query()
                ->whereIn('payment_attempt_id', $attempts->modelKeys())
                ->orderBy('id')
                ->get();
            $paidAmount = (int) $allocations->sum('amount');
            abort_unless($refundAmount <= $paidAmount, 409, 'The refund difference exceeds the amount received for this booking.');

            $policySnapshot = data_get($locked->metadata, 'booking_policy_snapshot');
            $refundDueHours = (int) data_get($policySnapshot, 'settings.cancellation.refund_due_hours', 48);
            $refundDueHours = $refundDueHours > 0 ? $refundDueHours : 48;

            $refund = OwnerRefundObligation::create([
                'organization_id' => $locked->organization_id,
                'venue_id' => $locked->court()->value('venue_id'),
                'reservation_id' => $locked->id,
                'booking_id' => LegacyBookingMapping::query()
                    ->withoutGlobalScope('tenant_isolation')
                    ->where('reservation_id', $locked->id)
                    ->value('legacy_booking_id'),
                'payer_id' => $payer->id,
                'payment_attempt_id' => $attempts->first()?->id,
                'reference_code' => 'RF'.Str::upper(Str::random(18)),
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $requestFingerprint,
                'slot_ids' => [],
                'gross_amount' => $refundAmount,
                'fee_amount' => 0,
                'amount' => $refundAmount,
                'currency' => $locked->currency,
                'status' => OwnerRefundObligation::STATUS_REQUESTED,
                'reason' => 'reschedule_difference:'.$replacementCode,
                'policy_snapshot' => is_array($policySnapshot) ? $policySnapshot : null,
                'recipient_ciphertext' => Crypt::encryptString(json_encode($recipient, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                'due_at' => now()->addHours($refundDueHours),
                'requested_at' => now(),
                'requested_by' => $payer->id,
            ]);

            foreach ($this->allocationShares($allocations, $refundAmount, 0) as $share) {
                if ($share['gross_amount'] <= 0) {
                    continue;
                }
                OwnerRefundAllocation::create([
                    'owner_refund_obligation_id' => $refund->id,
                    'owner_payment_allocation_id' => $share['allocation_id'],
                    'gross_amount' => $share['gross_amount'],
                    'fee_amount' => $share['fee_amount'],
                    'amount' => $share['amount'],
                    'created_at' => now(),
                ]);
            }

            $this->recordEvidence($refund, 'requested', $payer->id, [
                'reschedule_of' => $locked->code,
                'replacement' => $replacementCode,
                'refund_amount' => $refundAmount,
                'recipient' => $recipient,
            ]);

            return $refund->load(['allocations.paymentAllocation', 'evidence']);
        });
    }

    public function approve(OwnerRefundObligation $refund, User $owner): OwnerRefundObligation
    {
        return DB::transaction(function () use ($refund, $owner): OwnerRefundObligation {
            $locked = OwnerRefundObligation::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $this->authorizeOwner($locked, $owner);
            if ($locked->status !== OwnerRefundObligation::STATUS_REQUESTED && $locked->approved_at !== null) {
                return $locked->load(['allocations.paymentAllocation', 'evidence']);
            }
            abort_unless($locked->status === OwnerRefundObligation::STATUS_REQUESTED, 409, 'Only a requested refund can be approved.');

            $reservation = Reservation::query()->withoutGlobalScope('tenant_isolation')->whereKey($locked->reservation_id)->lockForUpdate()->firstOrFail();
            $slotIds = array_map('intval', (array) $locked->slot_ids);
            $slots = $reservation->slots()->whereIn('id', $slotIds)->where('status', 'confirmed')->lockForUpdate()->get();
            abort_unless($slots->count() === count($slotIds), 409, 'One or more requested slots are no longer active.');

            $now = now();
            $reservation->slots()->whereIn('id', $slotIds)->update(['status' => 'cancelled']);
            ReservationEntitlement::query()
                ->where('reservation_id', $reservation->id)
                ->whereIn('reservation_slot_id', $slotIds)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            $remainingSlots = $reservation->slots()->whereIn('status', ['held', 'confirmed'])->exists();
            if (! $remainingSlots) {
                $reservation->forceFill(['status' => 'cancelled', 'cancelled_at' => $now])->save();
            }

            $pointsToRestore = $this->pointsForSlots($reservation, $slotIds);
            if ($pointsToRestore > 0) {
                User::query()->whereKey($reservation->user_id)->lockForUpdate()->firstOrFail();
                $legacyBookingId = $reservation->legacyMapping()->value('legacy_booking_id');
                $sourceIds = array_values(array_filter([(int) $reservation->id, (int) $legacyBookingId]));
                $spentPoints = abs((int) PointLedger::query()
                    ->where('organization_id', $reservation->organization_id)
                    ->where('user_id', $reservation->user_id)
                    ->whereIn('source_type', ['reservation', 'booking'])
                    ->whereIn('source_id', $sourceIds)
                    ->where('delta', '<', 0)
                    ->sum('delta'));
                $pointsToRestore = min($pointsToRestore, $spentPoints);
            }
            if ($pointsToRestore > 0) {
                $balance = (int) PointLedger::query()
                    ->where('organization_id', $reservation->organization_id)
                    ->where('user_id', $reservation->user_id)
                    ->sum('delta');
                PointLedger::create([
                    'organization_id' => $reservation->organization_id,
                    'user_id' => $reservation->user_id,
                    'loyalty_rule_id' => data_get($reservation->metadata, 'loyalty_snapshot.rule_id'),
                    'delta' => $pointsToRestore,
                    'balance_after' => $balance + $pointsToRestore,
                    'source_type' => 'reservation_refund',
                    'source_id' => $locked->id,
                    'metadata' => [
                        'refund_reference' => $locked->reference_code,
                        'reservation_code' => $reservation->code,
                        'rule_version' => data_get($reservation->metadata, 'loyalty_snapshot.rule_version'),
                        'points_restored' => $pointsToRestore,
                    ],
                ]);
            }

            $this->releaseCouponIfFullyCancelled($reservation, $locked, $owner);

            $locked->forceFill([
                'status' => OwnerRefundObligation::STATUS_APPROVED,
                'approved_by' => $owner->id,
                'approved_at' => $now,
            ])->save();
            $this->recordEvidence($locked, 'approved', $owner->id, [
                'approved_slot_ids' => $slotIds,
                'points_restored' => $pointsToRestore,
                'reservation_status' => $reservation->fresh()->status,
            ]);

            return $locked->fresh(['allocations.paymentAllocation', 'evidence']);
        });
    }

    public function reject(OwnerRefundObligation $refund, User $owner, string $reason): OwnerRefundObligation
    {
        return DB::transaction(function () use ($refund, $owner, $reason): OwnerRefundObligation {
            $locked = OwnerRefundObligation::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $this->authorizeOwner($locked, $owner);
            if ($locked->status === OwnerRefundObligation::STATUS_REJECTED && $locked->decision_reason === trim($reason)) {
                return $locked->load(['allocations.paymentAllocation', 'evidence']);
            }
            abort_unless($locked->status === OwnerRefundObligation::STATUS_REQUESTED, 409, 'Only a requested refund can be rejected.');

            $locked->forceFill([
                'status' => OwnerRefundObligation::STATUS_REJECTED,
                'rejected_by' => $owner->id,
                'rejected_at' => now(),
                'decision_reason' => trim($reason),
            ])->save();
            $this->recordEvidence($locked, 'rejected', $owner->id, ['reason' => trim($reason)]);

            return $locked->fresh(['allocations.paymentAllocation', 'evidence']);
        });
    }

    public function reportExecution(OwnerRefundObligation $refund, User $owner, string $transferReference, string $evidence): OwnerRefundObligation
    {
        return DB::transaction(function () use ($refund, $owner, $transferReference, $evidence): OwnerRefundObligation {
            $locked = OwnerRefundObligation::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            $this->authorizeOwner($locked, $owner);
            if ($locked->owner_reported_at !== null && $locked->execution_reference === trim($transferReference)) {
                return $locked->load(['allocations.paymentAllocation', 'evidence']);
            }
            abort_unless($locked->status === OwnerRefundObligation::STATUS_APPROVED, 409, 'Only an approved refund can be reported as executed.');

            $report = ['transfer_reference' => trim($transferReference), 'evidence' => trim($evidence), 'reported_at' => now()->toIso8601String()];
            $locked->forceFill([
                'status' => OwnerRefundObligation::STATUS_OWNER_REPORTED,
                'execution_reference' => trim($transferReference),
                'execution_evidence_ciphertext' => Crypt::encryptString(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                'owner_reported_by' => $owner->id,
                'owner_reported_at' => now(),
            ])->save();
            $this->recordEvidence($locked, 'owner_reported', $owner->id, $report);

            return $locked->fresh(['allocations.paymentAllocation', 'evidence']);
        });
    }

    public function acknowledge(OwnerRefundObligation $refund, User $payer, bool $accepted, ?string $note = null): OwnerRefundObligation
    {
        return DB::transaction(function () use ($refund, $payer, $accepted, $note): OwnerRefundObligation {
            $locked = OwnerRefundObligation::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->payer_id === (int) $payer->id, 404);
            if ($locked->status === ($accepted ? OwnerRefundObligation::STATUS_ACKNOWLEDGED : OwnerRefundObligation::STATUS_DISPUTED)) {
                return $locked->load(['allocations.paymentAllocation', 'evidence']);
            }
            abort_unless($locked->status === OwnerRefundObligation::STATUS_OWNER_REPORTED, 409, 'Only a reported owner transfer can be acknowledged.');

            $response = ['accepted' => $accepted, 'note' => trim((string) $note), 'responded_at' => now()->toIso8601String()];
            $locked->forceFill([
                'status' => $accepted ? OwnerRefundObligation::STATUS_ACKNOWLEDGED : OwnerRefundObligation::STATUS_DISPUTED,
                'recipient_response_ciphertext' => Crypt::encryptString(json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                'recipient_acknowledged_by' => $payer->id,
                'recipient_acknowledged_at' => $accepted ? now() : null,
            ])->save();
            $this->recordEvidence($locked, $accepted ? 'recipient_acknowledged' : 'recipient_disputed', $payer->id, $response);
            if ($accepted) {
                $this->financials->postDirectOwnerRefund($locked->fresh(['allocations.paymentAllocation', 'paymentAttempt']));
            }

            return $locked->fresh(['allocations.paymentAllocation', 'evidence']);
        });
    }

    public function serialize(OwnerRefundObligation $refund): array
    {
        $recipient = $refund->recipient();

        return [
            'id' => (int) $refund->id,
            'reference_code' => $refund->reference_code,
            'reservation_code' => $refund->reservation?->code,
            'booking_id' => $refund->booking_id,
            'payer_id' => (int) $refund->payer_id,
            'status' => $refund->status,
            'gross_amount' => (int) $refund->gross_amount,
            'fee_amount' => (int) $refund->fee_amount,
            'refund_amount' => (int) $refund->amount,
            'currency' => $refund->currency,
            'slot_ids' => $refund->slot_ids,
            'legacy_slot_ids' => $refund->booking_id
                ? LegacyBookingSlotMapping::query()
                    ->where('legacy_booking_mapping_id', LegacyBookingMapping::query()->withoutGlobalScope('tenant_isolation')->where('legacy_booking_id', $refund->booking_id)->value('id'))
                    ->whereIn('reservation_slot_id', (array) $refund->slot_ids)
                    ->pluck('legacy_booking_slot_id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                : null,
            'reason' => $refund->reason,
            'recipient' => $recipient,
            'execution_reference' => $refund->execution_reference,
            'due_at' => $refund->due_at?->toIso8601String(),
            'requested_at' => $refund->requested_at?->toIso8601String(),
            'approved_at' => $refund->approved_at?->toIso8601String(),
            'owner_reported_at' => $refund->owner_reported_at?->toIso8601String(),
            'recipient_acknowledged_at' => $refund->recipient_acknowledged_at?->toIso8601String(),
            'evidence' => $refund->evidence->map(fn (OwnerRefundEvidence $event) => [
                'id' => (int) $event->id,
                'event_type' => $event->event_type,
                'actor_id' => $event->actor_id,
                'event_fingerprint' => $event->event_fingerprint,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function calculate(Reservation $reservation, array $slotIds): array
    {
        $slotIds = collect($slotIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
        abort_if($slotIds === [], 422, 'Select at least one reservation slot to cancel.');
        abort_unless(in_array($reservation->status, ['confirmed', 'in_use'], true), 409, 'Only a confirmed reservation can be cancelled for refund.');

        $policySnapshot = data_get($reservation->metadata, 'booking_policy_snapshot');
        if (! is_array($policySnapshot)) {
            $legacyMapping = $reservation->legacyMapping()->first();
            $booking = $legacyMapping ? Booking::query()->find($legacyMapping->legacy_booking_id) : null;
            $policySnapshot = data_get($booking?->policy_snapshot, 'venue_booking_policy');
        }
        abort_unless(is_array($policySnapshot) && is_array($policySnapshot['settings']['cancellation'] ?? null), 409, 'The accepted cancellation policy snapshot is unavailable for this reservation.');

        $cancellation = $policySnapshot['settings']['cancellation'];
        abort_unless($reservation->payment_model === 'direct_owner_v1', 409, 'Owner refunds are available only for direct-owner reservations.');
        $feeType = (string) ($cancellation['fee_type'] ?? '');
        $feeValue = (int) ($cancellation['fee_value'] ?? -1);
        abort_unless(in_array($feeType, ['none', 'fixed', 'percentage'], true) && $feeValue >= 0, 409, 'The captured cancellation fee policy is invalid.');
        abort_if($feeType === 'percentage' && $feeValue > 100, 409, 'The captured cancellation percentage is invalid.');
        $allSlots = $reservation->slots()->orderBy('starts_at')->orderBy('id')->get();
        $selectedSlots = $allSlots->whereIn('id', $slotIds)->where('status', 'confirmed')->values();
        abort_unless($selectedSlots->count() === count($slotIds), 409, 'One or more selected slots are no longer active.');
        $earliestStart = $selectedSlots->min('starts_at');
        $deadline = Carbon::parse($earliestStart)->subHours((int) ($cancellation['deadline_hours'] ?? 0));
        abort_if(now()->greaterThan($deadline), 422, 'The captured cancellation deadline has passed.');

        $attempts = OwnerPaymentAttempt::query()
            ->where('organization_id', $reservation->organization_id)
            ->whereIn('purpose', ['booking', 'split_share'])
            ->where('purpose_id', $reservation->code)
            ->where('status', OwnerPaymentAttempt::STATUS_PAID)
            ->get();
        $allocations = OwnerPaymentAllocation::query()
            ->whereIn('payment_attempt_id', $attempts->modelKeys())
            ->orderBy('id')
            ->get();
        $paidAmount = (int) $allocations->sum('amount');
        $paidBySlot = $this->weightedShares($allSlots, $paidAmount, fn (ReservationSlot $slot) => (int) $slot->unit_price);
        $grossAmount = (int) $selectedSlots->sum(fn (ReservationSlot $slot) => $paidBySlot[$slot->id] ?? 0);

        $feeAmount = match ($feeType) {
            'none' => 0,
            'fixed' => min($grossAmount, $feeValue),
            'percentage' => intdiv($grossAmount * min(100, max(0, $feeValue)), 100),
            default => abort(409, 'The captured cancellation fee policy is invalid.'),
        };
        $refundAmount = max(0, $grossAmount - $feeAmount);
        $allocationShares = $this->allocationShares($allocations, $grossAmount, $feeAmount);
        $refundDueHours = (int) ($cancellation['refund_due_hours'] ?? 0);
        abort_unless($refundDueHours > 0, 409, 'The captured refund deadline is invalid.');

        return [
            'attempt' => $attempts->first(),
            'attempt_id' => $attempts->first()?->id,
            'policy_snapshot' => $policySnapshot,
            'gross_amount' => $grossAmount,
            'fee_amount' => $feeAmount,
            'refund_amount' => $refundAmount,
            'allocation_shares' => $allocationShares,
            'due_at' => now()->addHours($refundDueHours),
            'points_to_restore' => $this->pointsForSlots($reservation, $slotIds),
            'public_calculation' => [
                'policy_version' => $policySnapshot['version'] ?? null,
                'gross_amount' => $grossAmount,
                'fee_type' => $feeType,
                'fee_value' => $feeValue,
                'fee_amount' => $feeAmount,
                'refund_amount' => $refundAmount,
                'currency' => $reservation->currency,
                'due_at' => now()->addHours($refundDueHours)->toIso8601String(),
            ],
        ];
    }

    private function weightedShares(Collection $items, int $totalAmount, callable $weight): array
    {
        $weights = $items->mapWithKeys(fn ($item) => [$item->id => max(0, (int) $weight($item))])->all();
        $totalWeight = array_sum($weights);
        if ($totalAmount <= 0 || $totalWeight <= 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $shares = [];
        $allocated = 0;
        foreach ($weights as $id => $itemWeight) {
            $shares[$id] = intdiv($totalAmount * $itemWeight, $totalWeight);
            $allocated += $shares[$id];
        }
        $remainder = $totalAmount - $allocated;
        foreach (array_keys($weights) as $id) {
            if ($remainder <= 0) {
                break;
            }
            $shares[$id]++;
            $remainder--;
        }

        return $shares;
    }

    private function allocationShares(Collection $allocations, int $grossAmount, int $feeAmount): array
    {
        $grossShares = $this->weightedShares($allocations, $grossAmount, fn (OwnerPaymentAllocation $allocation) => (int) $allocation->amount);
        $feeShares = $this->weightedShares($allocations, $feeAmount, fn (OwnerPaymentAllocation $allocation) => (int) ($grossShares[$allocation->id] ?? 0));

        return $allocations->map(fn (OwnerPaymentAllocation $allocation) => [
            'allocation_id' => (int) $allocation->id,
            'gross_amount' => (int) ($grossShares[$allocation->id] ?? 0),
            'fee_amount' => (int) ($feeShares[$allocation->id] ?? 0),
            'amount' => max(0, (int) ($grossShares[$allocation->id] ?? 0) - (int) ($feeShares[$allocation->id] ?? 0)),
        ])->values()->all();
    }

    private function pointsForSlots(Reservation $reservation, array $slotIds): int
    {
        $points = (int) ($reservation->loyalty_points_redeemed ?? 0);
        if ($points <= 0) {
            return 0;
        }
        $slots = $reservation->slots()->orderBy('starts_at')->orderBy('id')->get();
        $shares = $this->weightedShares($slots, $points, fn (ReservationSlot $slot) => (int) $slot->unit_price);

        return (int) collect($slotIds)->sum(fn ($id) => $shares[(int) $id] ?? 0);
    }

    private function assertSlotsNotAlreadyRequested(Reservation $reservation, array $slotIds): void
    {
        $active = OwnerRefundObligation::query()
            ->where('reservation_id', $reservation->id)
            ->whereIn('status', [
                OwnerRefundObligation::STATUS_REQUESTED,
                OwnerRefundObligation::STATUS_APPROVED,
                OwnerRefundObligation::STATUS_OWNER_REPORTED,
                OwnerRefundObligation::STATUS_ACKNOWLEDGED,
                OwnerRefundObligation::STATUS_DISPUTED,
            ])
            ->get(['slot_ids']);
        foreach ($active as $obligation) {
            abort_if(array_intersect($slotIds, array_map('intval', (array) $obligation->slot_ids)) !== [], 409, 'A refund request already covers one or more selected slots.');
        }
    }

    private function authorizeOwner(OwnerRefundObligation $refund, User $owner): void
    {
        $venue = $refund->reservation?->court?->venue ?? Venue::findOrFail($refund->venue_id);
        abort_unless(
            $owner->hasRoleInOrganization((int) $refund->organization_id, OrganizationMember::ROLE_ORGANIZATION_OWNER)
                && $owner->canManageVenue($venue),
            403,
            'Only the authorized venue owner can manage this refund.'
        );
    }

    private function releaseCouponIfFullyCancelled(Reservation $reservation, OwnerRefundObligation $refund, User $owner): void
    {
        if ($reservation->slots()->whereIn('status', ['held', 'confirmed', 'active', 'in_use'])->exists()) {
            return;
        }

        $metadata = (array) ($reservation->metadata ?? []);
        $couponId = (int) ($metadata['coupon_id'] ?? 0);
        $couponSnapshot = $metadata['coupon_usage_snapshot'] ?? [];
        if (
            $couponId <= 0
            || empty($couponSnapshot['usage_count_reserved'])
            || empty($couponSnapshot['release_on_full_cancellation'])
            || ! empty($metadata['coupon_usage_released_at'])
        ) {
            return;
        }

        $coupon = Coupon::query()->whereKey($couponId)->first();
        app(DiscountService::class)->release($coupon);
        $metadata['coupon_usage_released_at'] = now()->toIso8601String();
        $metadata['coupon_usage_released_by_refund'] = $refund->reference_code;
        $reservation->forceFill(['metadata' => $metadata])->save();
    }

    public function recordEvidence(OwnerRefundObligation $refund, string $type, ?int $actorId, array $content): void
    {
        $serialized = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        OwnerRefundEvidence::create([
            'owner_refund_obligation_id' => $refund->id,
            'event_type' => $type,
            'actor_id' => $actorId,
            'event_fingerprint' => hash('sha256', $refund->id.'|'.$type.'|'.$serialized),
            'evidence_ciphertext' => Crypt::encryptString($serialized),
            'created_at' => now(),
        ]);
    }
}
