<?php

namespace App\Services\Reservation;

use App\Models\Court;
use App\Models\VenueOperatingSchedule;
use App\Models\VenueScheduleException;
use Carbon\Carbon;

class VenueOperatingCalendar
{
    public function isOpen(Court $court, Carbon $startsAt, Carbon $endsAt): bool
    {
        $venue = $court->venue;
        if (! $venue) {
            return false;
        }

        $schedule = $this->scheduleFor($court);
        $exception = $this->exceptionFor($court, $startsAt->copy()->setTimezone($venue->timezone)->toDateString());
        if (! $schedule && ! $exception) {
            // Older venues retain their pre-calendar behavior until configured.
            return true;
        }

        $timezone = $schedule?->timezone ?: $exception?->timezone ?: $venue->timezone;
        $start = $startsAt->copy()->setTimezone($timezone);
        $end = $endsAt->copy()->setTimezone($timezone);
        if ($start->toDateString() !== $end->copy()->subSecond()->toDateString()) {
            return false;
        }

        $exception = $this->exceptionFor($court, $start->toDateString());
        if ($exception) {
            $intervals = $exception->intervals ?? [];
            if ($exception->type === 'closed' || ($exception->type === 'holiday' && $intervals === [])) {
                return false;
            }
        } else {
            $day = $schedule->weekly_hours[(string) $start->dayOfWeekIso]
                ?? $schedule->weekly_hours[$start->dayOfWeekIso]
                ?? null;
            if (! $day || ($day['closed'] ?? true)) {
                return false;
            }
            $intervals = $day['intervals'] ?? [];
        }

        $minute = fn (Carbon $time): int => ((int) $time->format('H')) * 60 + (int) $time->format('i');
        $startMinute = $minute($start);
        $endMinute = $minute($end);

        foreach ($intervals as $interval) {
            $open = Carbon::createFromFormat('H:i', $interval['start'], $timezone);
            $close = Carbon::createFromFormat('H:i', $interval['end'], $timezone);
            if ($startMinute >= $minute($open) && $endMinute <= $minute($close)) {
                return true;
            }
        }

        return false;
    }

    public function scheduleFor(Court $court): ?VenueOperatingSchedule
    {
        return VenueOperatingSchedule::query()
            ->where('venue_id', $court->venue_id)
            ->where('scope_key', 'court:'.$court->id)
            ->first()
            ?? VenueOperatingSchedule::query()
                ->where('venue_id', $court->venue_id)
                ->where('scope_key', 'venue')
                ->first();
    }

    public function exceptionFor(Court $court, string $date): ?VenueScheduleException
    {
        return VenueScheduleException::query()
            ->where('venue_id', $court->venue_id)
            ->where('scope_key', 'court:'.$court->id)
            ->whereDate('exception_date', $date)
            ->first()
            ?? VenueScheduleException::query()
                ->where('venue_id', $court->venue_id)
                ->where('scope_key', 'venue')
                ->whereDate('exception_date', $date)
                ->first();
    }

    public function configuredForVenue(int $venueId): bool
    {
        $courts = Court::query()->with('venue')->where('venue_id', $venueId)->where('is_active', true)->get();
        if ($courts->isEmpty()) {
            return false;
        }

        foreach ($courts as $court) {
            $schedule = $this->scheduleFor($court);
            if (! $schedule || ! collect($schedule->weekly_hours)->contains(fn ($day) => ! ($day['closed'] ?? true) && count($day['intervals'] ?? []) > 0)) {
                return false;
            }
        }

        return true;
    }
}
