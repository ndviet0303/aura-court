<?php

namespace App\Services\Reservation;

use App\Models\OwnerPaymentAttempt;
use App\Models\PointLedger;
use App\Models\Reservation;
use App\Notifications\AuraBookingNotification;
use App\Services\Booking\QuoteCouponService;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single release of an unpaid hold (design D2), shared by the payment-deadline sweep, the release of an
 * overdue hold before a new booking, the customer's payment cancellation and a price change. Every caller locks
 * through lock() first, so the order matches receipt processing (payment attempt, then reservation).
 */
class HeldReservationRelease
{
    public function __construct(private readonly QuoteCouponService $quoteCoupons) {}

    /** Lock the reservation's pending payment attempts, then the reservation. */
    public function lock(Reservation $candidate): ?Reservation
    {
        $this->pendingAttempts((string) $candidate->code)->lockForUpdate()->get();

        return Reservation::query()
            ->withoutGlobalScope('tenant_isolation')
            ->whereKey($candidate->id)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Release court-time, voucher use, reserved points and the pending payment attempt of a locked held
     * reservation, each once. A reservation that is no longer held is left as it is.
     */
    public function releaseHeld(Reservation $locked, string $status, string $reason): bool
    {
        if ($locked->status !== 'held') {
            return false;
        }

        $this->quoteCoupons->release($locked);
        $locked->forceFill([
            'status' => $status,
            'cancelled_at' => $status === 'cancelled' ? now() : $locked->cancelled_at,
            'metadata' => [...(array) $locked->metadata, 'release_reason' => $reason],
        ])->save();
        $locked->slots()->where('status', 'held')->update(['status' => $status]);
        $locked->entitlements()->where('status', 'held')->update(['status' => $status]);
        // Ordinary checkout no longer spends points; holds created before that still give them back.
        $this->releaseReservedPoints('reservation', (int) $locked->id, $reason);
        $this->pendingAttempts((string) $locked->code)->update(['status' => OwnerPaymentAttempt::STATUS_EXPIRED]);

        return true;
    }

    public function notifyPaymentDeadlinePassed(Reservation $reservation): void
    {
        $reservation->user?->notify(new AuraBookingNotification(
            'booking_expired',
            'Phiên giữ sân đã hết hạn',
            "Đơn đặt {$reservation->code} đã hết thời gian thanh toán và được giải phóng.",
            '/booking',
        ));
    }

    /** @return Builder<OwnerPaymentAttempt> */
    private function pendingAttempts(string $code): Builder
    {
        return OwnerPaymentAttempt::query()
            ->whereIn('purpose', ['booking', 'recurring_period'])
            ->where('purpose_id', $code)
            ->where('status', OwnerPaymentAttempt::STATUS_PENDING);
    }

    /**
     * Restore loyalty points reserved at hold time. Uses an append-only positive reversal keyed by a distinct
     * source type so the release is idempotent even across scheduler retries.
     */
    private function releaseReservedPoints(string $reserveType, int $sourceId, string $reason): void
    {
        $reserved = PointLedger::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('source_type', $reserveType)
            ->where('source_id', $sourceId)
            ->where('delta', '<', 0)
            ->get();
        if ($reserved->isEmpty()) {
            return;
        }

        $points = -(int) $reserved->sum('delta');
        if ($points <= 0) {
            return;
        }

        $first = $reserved->first();
        $orgId = (int) $first->organization_id;
        $userId = (int) $first->user_id;
        $releaseType = $reserveType.'_release';

        $alreadyReleased = PointLedger::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->where('source_type', $releaseType)
            ->where('source_id', $sourceId)
            ->exists();
        if ($alreadyReleased) {
            return;
        }

        $currentBalance = (int) PointLedger::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->sum('delta');

        PointLedger::create([
            'organization_id' => $orgId,
            'user_id' => $userId,
            'loyalty_rule_id' => $first->loyalty_rule_id,
            'delta' => $points,
            'balance_after' => $currentBalance + $points,
            'source_type' => $releaseType,
            'source_id' => $sourceId,
            'metadata' => [
                'released_reason' => $reason,
                'reserve_source_type' => $reserveType,
            ],
        ]);
    }
}
