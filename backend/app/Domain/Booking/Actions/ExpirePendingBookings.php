<?php

namespace App\Domain\Booking\Actions;

use App\Models\Reservation;
use App\Services\Booking\WaitlistService;
use App\Services\Reservation\HeldReservationRelease;
use Illuminate\Support\Facades\DB;

class ExpirePendingBookings
{
    public function __construct(
        private readonly HeldReservationRelease $release,
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
                $reservation = $this->release->lock($candidate);
                if (! $reservation || $reservation->status !== 'held' || ! $reservation->hold_expires_at?->isPast()) {
                    continue;
                }
                $reservation->load(['user', 'slots']);
                foreach ($reservation->slots->where('status', 'held') as $slot) {
                    $slots[] = [$slot->court_id, $slot->starts_at];
                }
                $this->release->releaseHeld($reservation, 'expired', 'payment_deadline');
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
            $this->release->notifyPaymentDeadlinePassed($reservation);
        }

        return $result['count'];
    }
}
