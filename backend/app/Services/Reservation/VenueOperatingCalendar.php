<?php

namespace App\Services\Reservation;

use App\Models\Court;
use App\Models\VenueOperatingSchedule;
use App\Models\VenueScheduleException;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * BR-31: a court is open where the venue's hours and the court's service schedule are both open. A dated exception
 * replaces the weekly hours of its own level on its date. UC-2.7 A-1, BR-33: a court without its own schedule is
 * closed and never inherits the venue's hours; without venue hours nothing is open either.
 *
 * A calendar is array{venue: ?VenueOperatingSchedule, court: ?VenueOperatingSchedule, exceptions: array<string,
 * array{venue?: VenueScheduleException, court?: VenueScheduleException}>}, the exceptions keyed by local date.
 */
class VenueOperatingCalendar
{
    public function isOpen(Court $court, Carbon $startsAt, Carbon $endsAt): bool
    {
        $venue = $court->venue;
        if (! $venue) {
            return false;
        }

        // ponytail: schedules carry their own timezone; every venue and schedule today is Asia/Ho_Chi_Minh.
        $timezone = $venue->timezone ?: config('app.timezone');
        $start = $startsAt->copy()->setTimezone($timezone);
        $end = $endsAt->copy()->setTimezone($timezone);
        if ($start->toDateString() !== $end->copy()->subSecond()->toDateString()) {
            return false;
        }

        // Counted from the start, so a slot ending at midnight ends at minute 1440 rather than 0.
        $startMinute = $start->hour * 60 + $start->minute;
        $endMinute = $startMinute + (int) round($start->diffInMinutes($end));

        foreach ($this->minutesOn($court, $start->toDateString()) as [$from, $to]) {
            if ($startMinute >= $from && $endMinute <= $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * The court's open [start, end) minute ranges of one local date.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function minutesOn(Court $court, string $date): array
    {
        $calendar = $this->calendarsFor(collect([$court]), $date, $date)[(int) $court->id];

        return self::dayMinutes($calendar, $date, CarbonImmutable::parse($date)->dayOfWeekIso);
    }

    /**
     * BR-31: the venue's own open minutes of a local date, its exception replacing the weekly hours; the outer
     * bound of a court exception on that date.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function venueMinutesOn(int $venueId, string $date): array
    {
        $schedule = VenueOperatingSchedule::query()->where('venue_id', $venueId)->where('scope_key', 'venue')->first();
        $exception = VenueScheduleException::query()
            ->where('venue_id', $venueId)
            ->where('scope_key', 'venue')
            ->whereDate('exception_date', $date)
            ->first();

        return self::openMinutes($schedule, $exception, CarbonImmutable::parse($date)->dayOfWeekIso);
    }

    /**
     * The court's effective weekly service time (venue hours intersected with its own schedule) in the stored
     * weekly_hours shape; null while the court has no schedule of its own (UC-2.7 A-1).
     *
     * @return ?array{timezone: string, weekly_hours: array<int, array{closed: bool, intervals: list<array{start: string, end: string}>}>}
     */
    public function serviceSchedule(Court $court): ?array
    {
        $rows = VenueOperatingSchedule::query()
            ->where('venue_id', $court->venue_id)
            ->whereIn('scope_key', ['venue', 'court:'.$court->id])
            ->get()
            ->keyBy('scope_key');
        $calendar = ['venue' => $rows->get('venue'), 'court' => $rows->get('court:'.$court->id), 'exceptions' => []];
        if ($calendar['court'] === null) {
            return null;
        }

        $weeklyHours = [];
        foreach (range(1, 7) as $weekday) {
            $intervals = array_map(
                fn (array $range): array => ['start' => self::clock($range[0]), 'end' => self::clock($range[1])],
                self::dayMinutes($calendar, null, $weekday),
            );
            $weeklyHours[$weekday] = ['closed' => $intervals === [], 'intervals' => $intervals];
        }

        return ['timezone' => $calendar['court']->timezone, 'weekly_hours' => $weeklyHours];
    }

    /**
     * The court's open [start, end) minute ranges of a local date, or of a plain weekday when $date is null.
     *
     * @param  array{venue: ?VenueOperatingSchedule, court: ?VenueOperatingSchedule, exceptions: array<string, array{venue?: VenueScheduleException, court?: VenueScheduleException}>}  $calendar
     * @return list<array{0: int, 1: int}>
     */
    public static function dayMinutes(array $calendar, ?string $date, int $weekday): array
    {
        $exceptions = $date === null ? [] : ($calendar['exceptions'][$date] ?? []);
        $venue = self::openMinutes($calendar['venue'], $exceptions['venue'] ?? null, $weekday);
        $court = self::openMinutes($calendar['court'], $exceptions['court'] ?? null, $weekday);
        $open = [];
        foreach ($venue as [$venueFrom, $venueTo]) {
            foreach ($court as [$courtFrom, $courtTo]) {
                if (max($venueFrom, $courtFrom) < min($venueTo, $courtTo)) {
                    $open[] = [max($venueFrom, $courtFrom), min($venueTo, $courtTo)];
                }
            }
        }
        usort($open, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $open;
    }

    /**
     * Open [start, end) minute ranges of one level (venue or court) on one day: the date's exception replaces the
     * weekly hours; without either nothing is open.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function openMinutes(?VenueOperatingSchedule $schedule, ?VenueScheduleException $exception, int $weekday): array
    {
        if ($exception) {
            $intervals = $exception->type === 'closed' ? [] : ($exception->intervals ?? []);
        } else {
            $day = $schedule?->weekly_hours[$weekday] ?? null;
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

    /** The "H:i" of a minute of the day; the end of the day reads 23:59, as the owner screens write it. */
    public static function clock(int $minute): string
    {
        return $minute >= 1440 ? '23:59' : sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }

    /**
     * Each court's venue and own schedules and its exceptions by local date from $fromDate (to $toDate), in two
     * queries for any number of courts.
     *
     * @param  Collection<int, Court>  $courts
     * @return array<int, array{venue: ?VenueOperatingSchedule, court: ?VenueOperatingSchedule, exceptions: array<string, array{venue?: VenueScheduleException, court?: VenueScheduleException}>}>
     */
    public function calendarsFor(Collection $courts, string $fromDate, ?string $toDate = null): array
    {
        $venueIds = $courts->pluck('venue_id')->unique()->values();
        $schedules = VenueOperatingSchedule::query()->whereIn('venue_id', $venueIds)->get();
        $exceptions = VenueScheduleException::query()
            ->whereIn('venue_id', $venueIds)
            ->whereDate('exception_date', '>=', $fromDate)
            ->when($toDate !== null, fn ($query) => $query->whereDate('exception_date', '<=', $toDate))
            ->get();

        return $courts->mapWithKeys(function (Court $court) use ($schedules, $exceptions): array {
            $levels = ['venue' => 'venue', 'court:'.$court->id => 'court'];
            $calendar = ['venue' => null, 'court' => null, 'exceptions' => []];
            foreach ($schedules->where('venue_id', $court->venue_id) as $schedule) {
                if (isset($levels[$schedule->scope_key])) {
                    $calendar[$levels[$schedule->scope_key]] = $schedule;
                }
            }
            foreach ($exceptions->where('venue_id', $court->venue_id) as $exception) {
                if (isset($levels[$exception->scope_key])) {
                    $calendar['exceptions'][$exception->exception_date->toDateString()][$levels[$exception->scope_key]] = $exception;
                }
            }

            return [(int) $court->id => $calendar];
        })->all();
    }
}
