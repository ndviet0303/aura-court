<?php

namespace Tests\Feature\Module02\Concerns;

use App\Models\Court;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Bookings written straight to the tables, as the booking flow leaves them: one hourly slot and entitlement each.
 *
 *     $booking = $this->booking($court, now()->addDay()->setTime(10, 0));          // confirmed, 10:00–11:00
 *     $held = $this->booking($court, $start, hours: 2, status: 'held');
 */
trait SeedsBookings
{
    /** @param  array<string, mixed>  $metadata */
    protected function booking(Court $court, CarbonInterface $start, int $hours = 1, string $status = 'confirmed', array $metadata = []): Reservation
    {
        $court->loadMissing('venue');
        $customer = User::factory()->create(['phone' => '09'.random_int(10000000, 99999999)]);
        $reservation = Reservation::create([
            'organization_id' => $court->venue->organization_id,
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'code' => 'RS'.Str::upper(Str::random(8)),
            'status' => $status,
            'currency' => 'VND',
            'total_amount' => 100000 * $hours,
            'hold_expires_at' => $status === 'held' ? now()->addMinutes(15) : null,
            'confirmed_at' => $status === 'confirmed' ? now() : null,
            'metadata' => $metadata,
        ]);
        for ($i = 0; $i < $hours; $i++) {
            $slotStart = $start->copy()->addHours($i)->utc();
            $slot = ReservationSlot::create([
                'organization_id' => $reservation->organization_id,
                'reservation_id' => $reservation->id,
                'court_id' => $court->id,
                'starts_at' => $slotStart,
                'ends_at' => $slotStart->copy()->addHour(),
                'unit_price' => 100000,
                'status' => $status,
            ]);
            ReservationEntitlement::create([
                'organization_id' => $reservation->organization_id,
                'reservation_id' => $reservation->id,
                'reservation_slot_id' => $slot->id,
                'user_id' => $customer->id,
                'court_id' => $court->id,
                'starts_at' => $slotStart,
                'ends_at' => $slotStart->copy()->addHour(),
                'amount' => 100000,
                'status' => $status === 'held' ? 'held' : 'active',
            ]);
        }

        return $reservation->fresh(['slots']);
    }

    /** A time tomorrow in the venue's timezone. */
    protected function tomorrowAt(int $hour, int $minute = 0): CarbonInterface
    {
        return now('Asia/Ho_Chi_Minh')->addDay()->setTime($hour, $minute);
    }
}
