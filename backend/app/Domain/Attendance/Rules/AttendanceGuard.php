<?php

namespace App\Domain\Attendance\Rules;

use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;

/** Module 06 checks shared by the attendance lookup and commands. Errors carry a code the frontend maps. */
final class AttendanceGuard
{
    /** BR-119: the booking belongs to the organization and the actor operates its venue (owner, or assigned staff). */
    public static function operable(?Reservation $reservation, User $actor): Reservation
    {
        if ($reservation === null) {
            self::fail('BOOKING_NOT_FOUND', 'Không tìm thấy đơn đặt sân trong tổ chức.', 404);
        }
        $venueId = $reservation->court?->venue_id;
        if ($venueId === null || ! $actor->canOperateVenue((int) $venueId)) {
            self::fail('VENUE_NOT_ASSIGNED', 'Bạn không được thao tác tại cơ sở của đơn này.', 403);
        }

        return $reservation;
    }

    /** BR-119: the schedule of a venue of the organization that the actor operates; another organization's is not found. */
    public static function venue(Venue $venue, int $organizationId, User $actor): Venue
    {
        if ((int) $venue->organization_id !== $organizationId) {
            self::fail('VENUE_NOT_FOUND', 'Không tìm thấy cơ sở trong tổ chức.', 404);
        }
        if (! $actor->canOperateVenue($venue)) {
            self::fail('VENUE_NOT_ASSIGNED', 'Bạn không được thao tác tại cơ sở này.', 403);
        }

        return $venue;
    }

    /** BR-125, BR-128, BR-133: commands apply to confirmed bookings only; check-in never changes that status (D3). */
    public static function confirmed(?Reservation $reservation, User $actor): Reservation
    {
        $reservation = self::operable($reservation, $actor);
        if ($reservation->status !== 'confirmed') {
            self::fail('BOOKING_NOT_CONFIRMED', 'Chỉ thao tác với đơn đã xác nhận.');
        }

        return $reservation;
    }

    public static function fail(string $code, string $message, int $status = 422): never
    {
        abort(response()->json(['message' => $message, 'code' => $code], $status));
    }
}
