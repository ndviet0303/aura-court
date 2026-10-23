<?php

namespace Tests\Feature\Module07\Concerns;

use App\Models\BookingAttendance;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Tests\Concerns\BuildsVenues;
use Tests\Feature\Module02\Concerns\SeedsBookings;

/**
 * Module 07 customers and chat: a booking of $user that module 06 checked out or marked no-show at a venue time.
 *
 *     $this->visit($court, $customer, $this->at('2026-12-31 23:30'));             // Hoàn thành
 *     $this->visit($court, $customer, $this->at('2026-11-03 10:00'), 'no_show');  // Không đến
 */
trait ServesCustomers
{
    use BuildsVenues;
    use SeedsBookings;

    protected function visit(Court $court, User $user, CarbonInterface $at, string $status = BookingAttendance::STATUS_CHECKED_OUT): Reservation
    {
        $booking = $this->booking($court, $at->copy()->subHour());
        $booking->update(['user_id' => $user->id]);
        BookingAttendance::create([
            'organization_id' => $booking->organization_id,
            'reservation_id' => $booking->id,
            'status' => $status,
            $status === BookingAttendance::STATUS_NO_SHOW ? 'no_show_at' : 'checked_out_at' => $at,
        ]);

        return $booking;
    }

    protected function at(string $time): CarbonInterface
    {
        return Carbon::parse($time, 'Asia/Ho_Chi_Minh');
    }

    /** @return array<string, string> */
    protected function tenant(Court|Venue $place): array
    {
        $venue = $place instanceof Court ? $place->venue : $place;

        return ['X-Organization-ID' => (string) $venue->organization_id];
    }
}
