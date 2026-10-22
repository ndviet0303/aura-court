<?php

namespace App\Domain\Venue\Rules;

use App\Services\Reservation\VenueOperatingCalendar;

/**
 * BR-31 for the intervals of one day: each ends after it starts, none overlaps another (one may end where the next
 * starts) and, for a court, each lies inside the venue's open hours of that weekday or date. Each error names the
 * offending interval by its field path and its times.
 */
final class ServiceIntervalRules
{
    public const WEEKDAYS = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'];

    /**
     * @param  array<int|string, array{start: string, end: string}>  $intervals  as submitted, in H:i
     * @param  string  $day  the weekday or date the messages name
     * @param  list<array{0: int, 1: int}>|null  $venueMinutes  the venue's open minutes that bound a court; null for the venue itself
     * @return list<array{0: string, 1: string}> [field path, message]
     */
    public static function errors(array $intervals, string $path, string $day, ?array $venueMinutes): array
    {
        $errors = [];
        $ranges = [];
        foreach ($intervals as $key => $interval) {
            $label = "{$interval['start']}–{$interval['end']}";
            $from = VenueOperatingCalendar::minuteOfDay($interval['start']);
            $to = VenueOperatingCalendar::minuteOfDay($interval['end'], true);
            if ($to <= $from) {
                $errors[] = ["{$path}.{$key}.end", "{$day}: khung giờ {$label} phải có giờ kết thúc sau giờ bắt đầu."];

                continue;
            }
            if ($venueMinutes !== null && ! self::inside($from, $to, $venueMinutes)) {
                $errors[] = ["{$path}.{$key}", "{$day}: khung giờ {$label} nằm ngoài giờ hoạt động của cơ sở (".self::describe($venueMinutes).').'];
            }
            $ranges[$key] = [$from, $to, $label];
        }

        uasort($ranges, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $latest = null;
        foreach ($ranges as $key => $range) {
            if ($latest !== null && $range[0] < $latest[1]) {
                $errors[] = ["{$path}.{$key}", "{$day}: khung giờ {$range[2]} chồng lên khung giờ {$latest[2]}."];
            }
            if ($latest === null || $range[1] > $latest[1]) {
                $latest = $range;
            }
        }

        return $errors;
    }

    /**
     * Inside the union of the venue's ranges, so venue intervals that touch count as one stretch.
     *
     * @param  list<array{0: int, 1: int}>  $venueMinutes
     */
    private static function inside(int $from, int $to, array $venueMinutes): bool
    {
        usort($venueMinutes, fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $cursor = $from;
        foreach ($venueMinutes as [$venueFrom, $venueTo]) {
            if ($venueFrom <= $cursor && $cursor < $venueTo) {
                $cursor = $venueTo;
            }
        }

        return $cursor >= $to;
    }

    /** @param  list<array{0: int, 1: int}>  $venueMinutes */
    private static function describe(array $venueMinutes): string
    {
        if ($venueMinutes === []) {
            return 'đóng cửa';
        }

        return implode(', ', array_map(
            fn (array $range): string => VenueOperatingCalendar::clock($range[0]).'–'.VenueOperatingCalendar::clock($range[1]),
            $venueMinutes,
        ));
    }
}
