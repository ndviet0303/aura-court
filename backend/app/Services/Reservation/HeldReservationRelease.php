<?php

namespace App\Services\Reservation;

use App\Contracts\Infrastructure\DurableNotificationServiceInterface;
use App\Exceptions\VoucherNoLongerApplies;
use App\Models\OwnerPaymentAttempt;
use App\Models\PointLedger;
use App\Models\Reservation;
use App\Notifications\AuraBookingNotification;
use App\Services\Promotion\VoucherUsageService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single release of an unpaid hold (design D2), shared by the payment-deadline sweep, the release of an
 * overdue hold before a new booking, the customer's payment cancellation, a price change and a voucher refused at
 * confirmation. Every caller locks
 * through lock() first, so the order matches receipt processing (payment attempt, then reservation).
 */
class HeldReservationRelease
{
    /** Customer notice per release reason that asks for a new request. */
    private const NOTICES = [
        'price_changed' => ['Giá sân đã thay đổi', 'Giá sân của đơn :code đã thay đổi nên yêu cầu đặt sân này không còn hiệu lực. Vui lòng xem giá mới và gửi lại yêu cầu.'],
        'voucher_changed' => ['Voucher không còn áp dụng', 'Voucher của đơn :code không còn áp dụng như lúc đặt nên yêu cầu đặt sân này không còn hiệu lực. Vui lòng gửi lại yêu cầu.'],
    ];

    public function __construct(
        private readonly VoucherUsageService $vouchers,
        private readonly DurableNotificationServiceInterface $notifications,
    ) {}

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

        $this->vouchers->release($locked);
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

    /**
     * Turn the voucher use of a locked held reservation into a used one at $confirmedAt (design D12). When the
     * voucher no longer applies, release the booking as voucher_changed and tell the customer, and return the refusal
     * instead of throwing it, so the caller's transaction keeps the release. Null when confirmation may go on.
     */
    public function consumeVoucher(Reservation $locked, CarbonInterface $confirmedAt): ?VoucherNoLongerApplies
    {
        try {
            $this->vouchers->consume($locked, $confirmedAt);

            return null;
        } catch (VoucherNoLongerApplies $refused) {
            if ($this->releaseHeld($locked, 'expired', 'voucher_changed')) {
                $this->notifyReleased($locked, 'voucher_changed');
            }

            return $refused;
        }
    }

    /** Queue the customer's notice in the caller's transaction, so a rollback sends nothing. */
    public function notifyReleased(Reservation $reservation, string $reason): void
    {
        [$title, $body] = self::NOTICES[$reason];
        $this->notifications->recordOutboxNotification(
            key: "reservation.notification.{$reservation->id}.{$reason}",
            userId: (int) $reservation->user_id,
            eventType: "booking.{$reason}",
            payload: [
                'booking_id' => $reservation->id,
                'booking_code' => $reservation->code,
                'event' => $reason,
                'title' => $title,
                'body' => strtr($body, [':code' => $reservation->code]),
                'action_url' => '/booking',
                'delivered_channels' => [],
                'mail_view' => 'emails.booking-notification',
                'aggregate_type' => Reservation::class,
                'aggregate_id' => $reservation->id,
            ],
            orgId: (int) $reservation->organization_id,
            channels: ['database', 'mail'],
        );
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
