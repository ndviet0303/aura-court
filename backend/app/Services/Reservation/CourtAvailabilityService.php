<?php

namespace App\Services\Reservation;

use App\Models\Court;
use App\Models\CourtBlock;
use App\Models\ReservationSlot;
use App\Services\Pricing\RentalPriceCalculator;
use Carbon\CarbonImmutable;

/** The times of one court on one local date (design D18): from its schedule, priced by the engine, unpriced left out. */
class CourtAvailabilityService
{
    public function __construct(
        private readonly VenueOperatingCalendar $calendar,
        private readonly RentalPriceCalculator $rentals,
    ) {}

    /** @return list<array{startsAt: string, endsAt: string, price: int, available: bool}> */
    public function forDate(Court $court, string $date): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $court->venue?->timezone ?: config('app.timezone'));
        $step = $this->step($court);
        $slots = [];
        foreach (VenueOperatingCalendar::openMinutes($this->calendar->scheduleFor($court), $this->calendar->exceptionFor($court, $date), $day->dayOfWeekIso) as [$from, $to]) {
            // Quotes take starts on a 30-minute mark only.
            for ($minute = (int) ceil($from / 30) * 30; $minute + $step <= $to; $minute += $step) {
                $slots[] = [$day->addMinutes($minute), $day->addMinutes($minute + $step)];
            }
        }
        $quotes = $this->rentals->quoteEach($court, $slots);
        if ($quotes === []) {
            return [];
        }

        $window = [$day->utc(), $day->addDay()->utc()];
        $busy = ReservationSlot::query()
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $window[1])
            ->where('ends_at', '>', $window[0])
            ->whereHas('reservation', fn ($reservation) => $reservation->blocksInventory())
            ->get(['starts_at', 'ends_at'])
            ->concat(CourtBlock::query()->where('court_id', $court->id)->where('starts_at', '<', $window[1])->where('ends_at', '>', $window[0])->get(['starts_at', 'ends_at']));

        $times = [];
        foreach ($quotes as $index => $quote) {
            [$start, $end] = $slots[$index];
            $times[] = [
                'startsAt' => $start->toIso8601String(),
                'endsAt' => $end->toIso8601String(),
                'price' => $quote->rental,
                'available' => $start->isFuture() && ! $busy->contains(fn ($row): bool => $row->starts_at < $end && $row->ends_at > $start),
            ];
        }

        return $times;
    }

    /** The grid step, here alone: the court's slot length while bookings send `slots` (design D29). */
    private function step(Court $court): int
    {
        return (int) $court->slot_duration;
    }
}
