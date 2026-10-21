<?php

namespace App\Services\Reservation;

use App\Models\Court;
use App\Models\VenueOperatingSchedule;
use App\Models\VenueScheduleException;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

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

        $intervals = self::openMinutes($schedule, $this->exceptionFor($court, $start->toDateString()), $start->dayOfWeekIso);

        // Counted from the start, so a slot ending at midnight ends at minute 1440 rather than 0.
        $startMinute = $start->hour * 60 + $start->minute;
        $endMinute = $startMinute + (int) round($start->diffInMinutes($end));

        foreach ($intervals as [$from, $to]) {
            if ($startMinute >= $from && $endMinute <= $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * Open [start, end) minute ranges of one local date. The date's exception replaces the weekly hours; a
     * court with neither is open all day, its pre-calendar behavior.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function openMinutes(?VenueOperatingSchedule $schedule, ?VenueScheduleException $exception, int $weekday): array
    {
        if ($exception) {
            $intervals = $exception->type === 'closed' ? [] : ($exception->intervals ?? []);
        } elseif (! $schedule) {
            return [[0, 1440]];
        } else {
            $day = $schedule->weekly_hours[$weekday] ?? null;
            $intervals = ! $day || ($day['closed'] ?? true) ? [] : ($day['intervals'] ?? []);
        }

        return array_map(fn (array $interval): array => [self::minuteOfDay($interval['start']), self::minuteOfDay($interval['end'], true)], array_values($intervals));
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

    /**
     * Each court's schedule and its exceptions by date from a local date on, in two queries for any number of
     * courts. The court's own row replaces the venue's, as scheduleFor() and exceptionFor() pick them.
     *
     * @param  Collection<int, Court>  $courts
     * @return array<int, array{schedule: ?VenueOperatingSchedule, exceptions: array<string, VenueScheduleException>}>
     */
    public function calendarsFor(Collection $courts, string $fromDate): array
    {
        $venueIds = $courts->pluck('venue_id')->unique()->values();
        $schedules = VenueOperatingSchedule::query()->whereIn('venue_id', $venueIds)->get();
        $exceptions = VenueScheduleException::query()->whereIn('venue_id', $venueIds)->whereDate('exception_date', '>=', $fromDate)->get();

        return $courts->mapWithKeys(function (Court $court) use ($schedules, $exceptions): array {
            $ownScope = fn (VenueOperatingSchedule|VenueScheduleException $row): bool => (int) $row->venue_id === (int) $court->venue_id
                && in_array($row->scope_key, ['venue', 'court:'.$court->id], true);
            $byDate = [];
            foreach ($exceptions->filter($ownScope)->sortBy(fn (VenueScheduleException $row): bool => $row->scope_key !== 'venue') as $exception) {
                $byDate[$exception->exception_date->toDateString()] = $exception;
            }

            return [(int) $court->id => [
                'schedule' => $schedules->filter($ownScope)->sortByDesc(fn (VenueOperatingSchedule $row): bool => $row->scope_key !== 'venue')->first(),
                'exceptions' => $byDate,
            ]];
        })->all();
    }

    /**
     * How many slots of their own length the courts can sell on the local dates from $from to $to, by their
     * schedules and exceptions.
     *
     * @param  Collection<int, Court>  $courts
     */
    public function slotCapacity(Collection $courts, CarbonInterface $from, CarbonInterface $to): int
    {
        $calendars = $this->calendarsFor($courts, $from->toDateString());
        $capacity = 0;
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay()) as $day) {
            foreach ($courts as $court) {
                $calendar = $calendars[(int) $court->id];
                foreach (self::openMinutes($calendar['schedule'], $calendar['exceptions'][$day->toDateString()] ?? null, $day->dayOfWeekIso) as [$open, $close]) {
                    $capacity += intdiv($close - $open, (int) $court->slot_duration);
                }
            }
        }

        return $capacity;
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
