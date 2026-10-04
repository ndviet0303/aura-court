<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\LegacyBookingMapping;
use App\Models\OwnerPaymentAttempt;
use App\Models\PointLedger;
use App\Models\Reservation;
use App\Notifications\AuraBookingNotification;
use App\Services\Booking\DiscountService;
use App\Services\Booking\WaitlistService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Illuminate\Support\Facades\DB;

class ExpirePendingBookings
{
    public function __construct(
        private readonly DiscountService $discounts,
        private readonly WaitlistService $waitlists,
    ) {}

    public function execute(int $limit = 200, ?array $organizationIds = null): int
    {
        $result = DB::transaction(function () use ($limit, $organizationIds) {
            $bookingCandidates = Booking::query()
                ->where('status', 'pending_payment')
                ->when($organizationIds !== null, fn ($query) => $query->whereHas('court.venue', fn ($venues) => $venues->whereIn('organization_id', $organizationIds)))
                ->where('payment_expires_at', '<', now())
                ->orderBy('id')
                ->limit($limit)
                ->get(['id', 'payment_model']);
            $slots = [];
            $expiredBookings = [];
            $reservationNotifications = [];

            foreach ($bookingCandidates as $candidate) {
                $mappingCandidate = LegacyBookingMapping::query()
                    ->withoutGlobalScope('tenant_isolation')
                    ->where('legacy_booking_id', $candidate->id)
                    ->first();
                $reservationCandidate = $mappingCandidate
                    ? Reservation::query()->withoutGlobalScope('tenant_isolation')->find($mappingCandidate->reservation_id)
                    : null;
                $attempt = null;
                if ($reservationCandidate && $candidate->payment_model === 'direct_owner_v1') {
                    $attempt = OwnerPaymentAttempt::query()
                        ->where('purpose', 'booking')
                        ->where('purpose_id', $reservationCandidate->code)
                        ->where('status', OwnerPaymentAttempt::STATUS_PENDING)
                        ->lockForUpdate()
                        ->first();
                }
                $reservation = $reservationCandidate
                    ? Reservation::query()->withoutGlobalScope('tenant_isolation')->whereKey($reservationCandidate->id)->lockForUpdate()->first()
                    : null;
                $mapping = $mappingCandidate
                    ? LegacyBookingMapping::query()->withoutGlobalScope('tenant_isolation')->whereKey($mappingCandidate->id)->lockForUpdate()->first()
                    : null;
                $booking = Booking::query()->whereKey($candidate->id)->lockForUpdate()->first();
                if (! $booking || $booking->status !== 'pending_payment' || ! $booking->payment_expires_at?->isPast()) {
                    continue;
                }
                $booking->loadMissing(['user', 'coupon', 'slots']);
                if (! $reservation) {
                    foreach ($booking->slots->where('status', 'held') as $slot) {
                        $slots[] = [$slot->court_id, $slot->starts_at];
                    }
                }
                $booking->update(['status' => 'expired']);
                $booking->slots()->where('status', 'held')->update(['status' => 'expired']);
                if ($booking->coupon_id && ! $booking->coupon_usage_released_at) {
                    $this->discounts->release($booking->coupon);
                    $booking->update(['coupon_usage_released_at' => now()]);
                }
                $this->releaseReservedPoints('booking', (int) $booking->id);

                if ($reservation && $reservation->status === 'held') {
                    foreach ($reservation->slots()->where('status', 'held')->get() as $slot) {
                        $slots[] = [$slot->court_id, $slot->starts_at];
                    }
                    $reservation->forceFill(['status' => 'expired'])->save();
                    $reservation->slots()->where('status', 'held')->update(['status' => 'expired']);
                    $reservation->entitlements()->where('status', 'held')->update(['status' => 'expired']);
                }
                if ($attempt) {
                    $attempt->forceFill(['status' => OwnerPaymentAttempt::STATUS_EXPIRED])->save();
                }
                if ($mapping) {
                    app(LegacyBookingBackfillService::class)->syncProjection($booking);
                }
                $expiredBookings[] = $booking;
            }

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
                    ->whereIn('purpose', ['booking', 'booking_change', 'recurring_period'])
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
                if ($reservation->legacyMapping()->exists()) {
                    continue; // V1 projections are expired with their canonical booking above.
                }
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
                'count' => count($expiredBookings) + count($reservationNotifications),
                'bookings' => $expiredBookings,
                'reservations' => $reservationNotifications,
                'slots' => $slots,
            ];
        });

        foreach ($result['bookings'] as $booking) {
            $booking->user?->notify(new AuraBookingNotification(
                'booking_expired',
                'Phiên giữ sân đã hết hạn',
                "Booking {$booking->code} đã hết thời gian thanh toán và được giải phóng.",
                '/booking',
            ));
        }

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
