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

        // Counted from the start, so a slot ending at midnight ends at minute 1440 rather than 0.
        $startMinute = $start->hour * 60 + $start->minute;
        $endMinute = $startMinute + (int) round($start->diffInMinutes($end));

        foreach ($intervals as $interval) {
            if ($startMinute >= self::minuteOfDay($interval['start']) && $endMinute <= self::minuteOfDay($interval['end'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Minutes since midnight of an "H:i" time. As an end bound, 00:00 and 23:59 mean the end of the
     * day, because H:i cannot express 24:00 and the owner screens offer 23:59 for "until midnight".
     */
    public static function minuteOfDay(string $time, bool $isEnd = false): int
    {
        $minute = (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);

        return $isEnd && in_array($minute, [0, 1439], true) ? 1440 : $minute;
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
