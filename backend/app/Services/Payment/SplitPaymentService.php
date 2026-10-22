<?php

namespace App\Services\Payment;

use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\SplitGroup;
use App\Models\SplitShare;
use App\Models\User;
use App\Services\Reservation\HeldReservationRelease;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SplitPaymentService
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly OwnerPaymentAttemptService $attempts,
        private readonly SplitExpiredShareExceptions $expiredShares,
    ) {}

    public function create(Reservation $reservation, User $host, array $shares): SplitGroup
    {
        $count = count($shares);
        if ($count < 2 || $count > 8) {
            throw ValidationException::withMessages(['shares' => 'A split group must have between two and eight shares.']);
        }
        $amounts = array_map(static fn ($share) => (int) (is_array($share) ? ($share['amount'] ?? 0) : $share), $shares);
        if (array_sum($amounts) !== (int) $reservation->total_amount || min($amounts) <= 0) {
            throw ValidationException::withMessages(['shares' => 'Split shares must be positive and sum exactly to the reservation amount.']);
        }

        return DB::transaction(function () use ($reservation, $host, $amounts): SplitGroup {
            $lockedReservation = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedReservation->status === 'held', 409, 'Only held reservations can be split.');

            $defaultExpiry = now()->addMinutes(15);
            $holdExpiry = $lockedReservation->hold_expires_at ? Carbon::parse($lockedReservation->hold_expires_at) : null;
            $expiresAt = $holdExpiry && $holdExpiry->lessThan($defaultExpiry) ? $holdExpiry : $defaultExpiry;

            $group = SplitGroup::create([
                'organization_id' => $lockedReservation->organization_id,
                'reservation_id' => $lockedReservation->id,
                'host_user_id' => $host->id,
                'expected_amount' => $lockedReservation->total_amount,
                'share_count' => count($amounts),
                'expires_at' => $expiresAt,
                'status' => 'pending',
                'paid_amount' => 0,
                'host_covers_shortfall' => false,
            ]);

            $venueId = $lockedReservation->court?->venue_id;
            $connection = ReceivingConnection::query()
                ->where('organization_id', $lockedReservation->organization_id)
                ->when($venueId, fn ($q) => $q->where('venue_id', $venueId))
                ->where('status', ReceivingConnection::STATUS_VERIFIED)
                ->first();

            foreach ($amounts as $index => $amount) {
                $share = SplitShare::create([
                    'organization_id' => $lockedReservation->organization_id,
                    'split_group_id' => $group->id,
                    'share_number' => $index + 1,
                    'amount' => $amount,
                    'paid_amount' => 0,
                    'status' => 'pending',
                    'expires_at' => $group->expires_at,
                ]);

                if ($connection) {
                    $reference = 'AC'.Str::upper(Str::random(18));
                    $this->attempts->createAttempt(
                        $connection,
                        null,
                        'split_share',
                        'split_group:'.$group->id.':share:'.$share->id,
                        $reference,
                        $amount,
                        strtoupper((string) ($lockedReservation->currency ?? 'VND')),
                        $share->expires_at,
                        'split:'.$group->id.':share:'.$share->id,
                    );
                    $share->update(['payment_reference' => $reference]);
                }
            }

            return $group->load('shares');
        });
    }

    public function ensureShareAttempt(SplitShare $share): ?OwnerPaymentAttempt
    {
        $existing = $this->attempts->latestForPurpose(
            'split_share',
            'split_group:'.$share->split_group_id.':share:'.$share->id
        );
        if ($existing) {
            return $existing;
        }

        if ($share->status !== 'pending' || ($share->expires_at && $share->expires_at->isPast())) {
            return null;
        }

        $group = $share->group;
        if (! $group || $group->status !== 'pending') {
            return null;
        }

        $reservation = $group->reservation;
        $venueId = $reservation?->court?->venue_id;
        $connection = ReceivingConnection::query()
            ->where('organization_id', $share->organization_id)
            ->when($venueId, fn ($q) => $q->where('venue_id', $venueId))
            ->where('status', ReceivingConnection::STATUS_VERIFIED)
            ->first();

        if (! $connection) {
            return null;
        }

        $reference = 'AC'.Str::upper(Str::random(18));
        $attempt = $this->attempts->createAttempt(
            $connection,
            null,
            'split_share',
            'split_group:'.$group->id.':share:'.$share->id,
            $reference,
            $share->amount,
            strtoupper((string) ($reservation->currency ?? 'VND')),
            $share->expires_at,
            'split:'.$group->id.':share:'.$share->id,
        );
        $share->update(['payment_reference' => $reference]);

        return $attempt;
    }

    public function payShare(SplitShare $share, User $payer, int $amount, string $paymentReference): SplitGroup
    {
        return DB::transaction(function () use ($share, $amount, $paymentReference): SplitGroup {
            $share = SplitShare::query()->whereKey($share->id)->lockForUpdate()->firstOrFail();
            $group = SplitGroup::query()->whereKey($share->split_group_id)->lockForUpdate()->firstOrFail();
            abort_unless($share->organization_id === $group->organization_id, 409);
            abort_unless($group->status === 'pending', 409, 'Split group is not awaiting payment.');
            abort_if($group->expires_at?->isPast() || $share->expires_at?->isPast(), 409, 'Split share has expired.');
            abort_unless($amount === (int) $share->amount, 422, 'A split share must be paid in full.');
            abort_unless($share->status === 'pending', 409, 'Split share has already been paid.');

            $allocation = OwnerPaymentAllocation::query()
                ->where('organization_id', $share->organization_id)
                ->where('purpose', 'split_share')
                ->where('purpose_id', 'split_group:'.$group->id.':share:'.$share->id)
                ->where('amount', $amount)
                ->whereHas('attempt', fn ($q) => $q->where('reference', trim($paymentReference))->where('status', OwnerPaymentAttempt::STATUS_PAID))
                ->first();

            abort_unless($allocation !== null, 422, 'Unverified payment reference: verified owner receipt is required.');

            if ($share->status === 'pending') {
                $this->confirmPaidAttempt($allocation, $allocation->receipt->event_at ?? now());
            }

            return $group->fresh('shares', 'reservation');
        });
    }

    public function coverShortfall(SplitGroup $group, User $host, ?string $paymentReference = null): SplitGroup
    {
        return DB::transaction(function () use ($group, $host, $paymentReference): SplitGroup {
            $group = SplitGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            abort_unless($group->host_user_id === $host->id, 403, 'Only the host can cover split shortfall.');
            abort_unless($group->status === 'pending', 409, 'Split group is not awaiting payment.');
            abort_if($group->expires_at?->isPast(), 409, 'Split group has expired.');
            $shortfall = (int) $group->expected_amount - (int) $group->paid_amount;
            abort_if($shortfall <= 0, 409, 'Split group has no shortfall.');

            $reservation = $group->reservation()->lockForUpdate()->firstOrFail();
            abort_unless($reservation->status === 'held', 409, 'Reservation hold is no longer active.');

            $venueId = $reservation->court?->venue_id;
            $connection = ReceivingConnection::query()
                ->where('organization_id', $group->organization_id)
                ->when($venueId, fn ($q) => $q->where('venue_id', $venueId))
                ->where('status', ReceivingConnection::STATUS_VERIFIED)
                ->first();

            abort_unless($connection !== null, 409, 'Venue does not have a verified direct receiving connection.');

            $existingAttempt = $this->attempts->latestForPurpose('split_share', 'split_group:'.$group->id.':coverage');
            if (! $existingAttempt || $existingAttempt->status !== OwnerPaymentAttempt::STATUS_PENDING || (int) $existingAttempt->amount !== $shortfall || ($existingAttempt->expires_at && $existingAttempt->expires_at->isPast())) {
                $reference = 'AC'.Str::upper(Str::random(18));
                $this->attempts->createAttempt(
                    $connection,
                    $host,
                    'split_share',
                    'split_group:'.$group->id.':coverage',
                    $reference,
                    $shortfall,
                    strtoupper((string) ($reservation->currency ?? 'VND')),
                    $group->expires_at,
                    'split:'.$group->id.':coverage:'.now()->timestamp,
                );
            }

            if ($paymentReference !== null && trim($paymentReference) !== '') {
                $allocation = OwnerPaymentAllocation::query()
                    ->where('organization_id', $group->organization_id)
                    ->where('purpose', 'split_share')
                    ->where('purpose_id', 'split_group:'.$group->id.':coverage')
                    ->where('amount', $shortfall)
                    ->whereHas('attempt', fn ($q) => $q->where('reference', trim($paymentReference))->where('status', OwnerPaymentAttempt::STATUS_PAID))
                    ->first();

                abort_unless($allocation !== null, 422, 'Unverified payment reference: verified owner receipt is required.');

                $this->confirmPaidAttempt($allocation, $allocation->receipt->event_at ?? now());
            }

            return $group->fresh('shares', 'reservation');
        });
    }

    public function parseAttemptTarget(string $purposeId): ?array
    {
        if (preg_match('/^split_group:(\d+):share:(\d+)$/', $purposeId, $matches)) {
            return [
                'group_id' => (int) $matches[1],
                'share_id' => (int) $matches[2],
                'is_coverage' => false,
            ];
        }

        if (preg_match('/^split_group:(\d+):coverage$/', $purposeId, $matches)) {
            return [
                'group_id' => (int) $matches[1],
                'share_id' => null,
                'is_coverage' => true,
            ];
        }

        return null;
    }

    public function hasConfirmableShare(OwnerPaymentAttempt $attempt): bool
    {
        $target = $this->parseAttemptTarget((string) $attempt->purpose_id);
        if ($target === null || $attempt->purpose !== 'split_share') {
            return false;
        }

        $group = SplitGroup::query()->withoutGlobalScope('tenant_isolation')->find($target['group_id']);
        if (! $group
            || (int) $group->organization_id !== (int) $attempt->organization_id
            || $group->status !== 'pending'
            || ($group->expires_at && $group->expires_at->isPast())) {
            return false;
        }

        $reservation = $group->reservation()->withoutGlobalScope('tenant_isolation')->first();
        if (! $reservation || $reservation->status !== 'held') {
            return false;
        }

        if ($target['is_coverage']) {
            $shortfall = (int) $group->expected_amount - (int) $group->paid_amount;

            return $shortfall > 0 && (int) $attempt->amount === $shortfall;
        }

        $share = $group->shares()->withoutGlobalScope('tenant_isolation')->find($target['share_id']);
        if (! $share || $share->status !== 'pending' || ($share->expires_at && $share->expires_at->isPast())) {
            return false;
        }

        return (int) $share->amount === (int) $attempt->amount;
    }

    public function confirmPaidAttempt(OwnerPaymentAllocation $allocation, CarbonInterface $paidAt): void
    {
        $attempt = $allocation->attempt;
        $receipt = $allocation->receipt;
        abort_unless(
            $attempt && $receipt
                && $attempt->status === OwnerPaymentAttempt::STATUS_PAID
                && $receipt->status === OwnerPaymentReceipt::STATUS_ALLOCATED
                && $receipt->receiving_connection_evidence_id !== null
                && $receipt->transfer_type === 'in'
                && (int) $receipt->payment_attempt_id === (int) $attempt->id
                && (int) $receipt->receiving_connection_id === (int) $attempt->receiving_connection_id
                && (int) $receipt->organization_id === (int) $attempt->organization_id
                && (int) $allocation->organization_id === (int) $attempt->organization_id
                && (int) $allocation->payment_attempt_id === (int) $attempt->id
                && (int) $allocation->receiving_connection_id === (int) $attempt->receiving_connection_id
                && (int) $allocation->amount === (int) $attempt->amount
                && (int) $receipt->amount === (int) $attempt->amount
                && $allocation->currency === $attempt->currency
                && $receipt->currency === $attempt->currency
                && $allocation->purpose === $attempt->purpose
                && $allocation->purpose_id === $attempt->purpose_id
                && $receipt->event_at?->equalTo($paidAt),
            409, 'A verified owner receipt allocation is required for a split share.'
        );

        $target = $this->parseAttemptTarget((string) $attempt->purpose_id);
        abort_unless($target !== null, 409, 'Invalid split attempt target.');

        $group = SplitGroup::query()->withoutGlobalScope('tenant_isolation')
            ->whereKey($target['group_id'])->lockForUpdate()->firstOrFail();
        abort_unless($this->hasConfirmableShare($attempt), 409, 'This split share is no longer payable.');

        if ($target['is_coverage']) {
            $shortfall = (int) $group->expected_amount - (int) $group->paid_amount;
            abort_unless($shortfall === (int) $attempt->amount, 409, 'Shortfall amount mismatch.');
            $group->update([
                'paid_amount' => $group->expected_amount,
                'host_covers_shortfall' => true,
            ]);
            $group->shares()->withoutGlobalScope('tenant_isolation')->where('status', 'pending')->lockForUpdate()->get()->each(function (SplitShare $share) use ($attempt, $paidAt): void {
                $share->update([
                    'payer_id' => $attempt->user_id,
                    'paid_amount' => $share->amount,
                    'payment_reference' => $attempt->reference,
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                ]);
            });
        } else {
            $share = $group->shares()->withoutGlobalScope('tenant_isolation')->whereKey($target['share_id'])->lockForUpdate()->firstOrFail();
            abort_unless($share->status === 'pending', 409, 'Split share already paid.');
            $share->update([
                'payer_id' => $attempt->user_id ?? $share->payer_id,
                'paid_amount' => (int) $allocation->amount,
                'payment_reference' => $attempt->reference,
                'status' => 'paid',
                'paid_at' => $paidAt,
            ]);
            $group->update([
                'paid_amount' => (int) $group->paid_amount + (int) $allocation->amount,
            ]);
        }

        if ((int) $group->paid_amount >= (int) $group->expected_amount) {
            $release = app(HeldReservationRelease::class);
            $reservation = $release->lock($group->reservation()->withoutGlobalScope('tenant_isolation')->firstOrFail());
            // D12: a voucher that no longer applies ends the group as expire() does, this transfer among its paid shares.
            if ($reservation?->status === 'held' && $release->consumeVoucher($reservation, $paidAt)) {
                $this->expire($group, true);

                return;
            }
            $group->update(['status' => 'confirmed', 'confirmed_at' => $paidAt]);
            if ($reservation?->status === 'held') {
                $reservation->forceFill(['status' => 'confirmed', 'confirmed_at' => $paidAt])->save();
                $reservation->slots()->withoutGlobalScope('tenant_isolation')->where('status', 'held')->update(['status' => 'confirmed']);
                $reservation->entitlements()->withoutGlobalScope('tenant_isolation')->where('status', 'held')->update(['status' => 'active']);
            }
        }
    }

    /**
     * Bounded, retry-safe sweep that expires every pending split group past its hold window,
     * delegating to the row-locked expire() so a concurrent payShare/coverShortfall serialises.
     */
    public function expireDue(int $limit = 200): int
    {
        $expired = 0;
        SplitGroup::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->limit($limit)
            ->get(['id'])
            ->each(function (SplitGroup $group) use (&$expired): void {
                if ($this->expire($group)->status === 'expired') {
                    $expired++;
                }
            });

        return $expired;
    }

    public function expire(SplitGroup $group, bool $beforeDue = false): SplitGroup
    {
        return DB::transaction(function () use ($group, $beforeDue): SplitGroup {
            $group = SplitGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            if ($group->status !== 'pending' || (! $beforeDue && $group->expires_at && $group->expires_at->isFuture())) {
                return $group;
            }
            $group->update(['status' => 'expired']);

            $reservation = $group->reservation()->withoutGlobalScope('tenant_isolation')->lockForUpdate()->first();
            if ($reservation && $reservation->status === 'held') {
                $this->reservations->release($reservation, 'expired');
            }

            $group->shares()->where('status', 'pending')->update(['status' => 'expired']);
            $this->expiredShares->record($group);
            // A receipt locks its attempt before the group, so attempts expire after commit, outside the group lock (D12).
            $purposeIds = $group->shares()->pluck('id')->map(fn ($id) => "split_group:{$group->id}:share:{$id}")->push("split_group:{$group->id}:coverage");
            DB::afterCommit(fn () => DB::transaction(fn () => $purposeIds->each(fn ($purposeId) => $this->attempts->expirePendingForPurpose('split_share', $purposeId))));

            return $group->fresh('shares', 'reservation');
        });
    }
}
