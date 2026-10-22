<?php

namespace Tests\Concerns;

use App\Models\Court;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;
use Illuminate\Support\Str;

/**
 * Venues and courts that take bookings: an active venue with an active package (VenueFactory), open 06:00–22:00
 * every day, and active courts priced all day (PricesCourts).
 *
 *     $court = $this->bookableCourt();                    // venue, schedule, court, 100.000 ₫/h
 *     $venue = $this->openVenue(); $court = $this->activeCourt($venue);
 */
trait BuildsVenues
{
    use PricesCourts;

    /** @param  array<string, mixed>  $attributes */
    protected function openVenue(array $attributes = [], string $opens = '06:00', string $closes = '22:00'): Venue
    {
        $venue = Venue::factory()->create($attributes);
        $this->weeklyHours($venue, $opens, $closes);

        return $venue;
    }

    protected function weeklyHours(Venue $venue, string $opens = '06:00', string $closes = '22:00'): VenueOperatingSchedule
    {
        $days = [];
        foreach (range(1, 7) as $day) {
            $days[(string) $day] = ['closed' => false, 'intervals' => [['start' => $opens, 'end' => $closes]]];
        }

        return VenueOperatingSchedule::updateOrCreate(
            ['venue_id' => $venue->id, 'scope_key' => 'venue'],
            ['version' => 1, 'timezone' => 'Asia/Ho_Chi_Minh', 'weekly_hours' => $days],
        );
    }

    /** @param  array<string, mixed>  $attributes */
    protected function activeCourt(Venue $venue, array $attributes = []): Court
    {
        return Court::create($attributes + [
            'venue_id' => $venue->id,
            'code' => 'S'.Str::upper(Str::random(4)),
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'status' => Court::STATUS_ACTIVE,
        ]);
    }

    /** An active, priced court of a new open venue. */
    protected function bookableCourt(int $pricePerHour = 100000): Court
    {
        $court = $this->activeCourt($this->openVenue());
        $this->priceCourt($court, $pricePerHour);

        return $court->load('venue');
    }
}
