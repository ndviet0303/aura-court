<?php

namespace App\Domain\Booking\Actions;

use App\Models\OwnerPaymentAttempt;
use App\Models\PointLedger;
use App\Models\Reservation;
use App\Notifications\AuraBookingNotification;
use App\Services\Booking\QuoteCouponService;
use App\Services\Booking\WaitlistService;
use Illuminate\Support\Facades\DB;

class ExpirePendingBookings
{
    public function __construct(
        private readonly QuoteCouponService $quoteCoupons,
        private readonly WaitlistService $waitlists,
    ) {}

    public function execute(int $limit = 200, ?array $organizationIds = null): int
    {
        $result = DB::transaction(function () use ($limit, $organizationIds) {
            $slots = [];
            $reservationNotifications = [];

            $reservationCandidates = Reservation::query()
                ->withoutGlobalScope('tenant_isolation')
                ->where('status', 'held')
                ->when($organizationIds !== null, fn ($query) => $query->whereIn('organization_id', $organizationIds))
                ->whereNotNull('hold_expires_at')
                ->where('hold_expires_at', '<', now())
                ->orderBy('id')
                ->limit($limit)
                ->get(['id', 'code']);
            foreach ($reservationCandidates as $candidate) {
                $attempt = OwnerPaymentAttempt::query()
                    ->whereIn('purpose', ['booking', 'recurring_period'])
                    ->where('purpose_id', $candidate->code)
                    ->where('status', OwnerPaymentAttempt::STATUS_PENDING)
                    ->lockForUpdate()
                    ->first();
                $reservation = Reservation::query()
                    ->withoutGlobalScope('tenant_isolation')
                    ->whereKey($candidate->id)
                    ->lockForUpdate()
                    ->with(['user', 'slots'])
                    ->first();
                if (! $reservation || $reservation->status !== 'held' || ! $reservation->hold_expires_at?->isPast()) {
                    continue;
                }
                $this->quoteCoupons->release($reservation);
                $reservation->forceFill(['status' => 'expired'])->save();
                foreach ($reservation->slots->where('status', 'held') as $slot) {
                    $slots[] = [$slot->court_id, $slot->starts_at];
                }
                $reservation->slots()->where('status', 'held')->update(['status' => 'expired']);
                $reservation->entitlements()->where('status', 'held')->update(['status' => 'expired']);
                $this->releaseReservedPoints('reservation', (int) $reservation->id);
                if ($attempt) {
                    $attempt->forceFill(['status' => OwnerPaymentAttempt::STATUS_EXPIRED])->save();
                }
                $reservationNotifications[] = $reservation;
            }

            return [
                'count' => count($reservationNotifications),
                'reservations' => $reservationNotifications,
                'slots' => $slots,
            ];
        });

        foreach ($result['slots'] as [$courtId, $startsAt]) {
            $this->waitlists->notifyForSlot($courtId, $startsAt);
        }

        foreach ($result['reservations'] as $reservation) {
            $reservation->user?->notify(new AuraBookingNotification(
                'booking_expired',
                'Phiên giữ sân đã hết hạn',
                "Đơn đặt {$reservation->code} đã hết thời gian thanh toán và được giải phóng.",
                '/booking',
            ));
        }

        return $result['count'];
    }

    /**
     * Restore loyalty points reserved at hold time when a hold expires. Uses an
     * append-only positive reversal keyed by a distinct source type so the release
     * is idempotent even across scheduler retries.
     */
    private function releaseReservedPoints(string $reserveType, int $sourceId): void
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
                'released_reason' => 'hold_expired',
                'reserve_source_type' => $reserveType,
            ],
        ]);
    }
}
