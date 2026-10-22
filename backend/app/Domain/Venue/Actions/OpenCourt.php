<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\OperationalStatusRepositoryInterface;
use App\Domain\Venue\Rules\LifecycleGuard;
use App\Events\CourtStatusUpdated;
use App\Exceptions\OpeningConditionsUnmetException;
use App\Models\Court;
use App\Models\Venue;
use App\Services\Pricing\PriceCoverageService;
use App\Services\Reservation\VenueOperatingCalendar;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * D3: the owner opens an inactive court once it has a code and a sport, a service schedule with an interval inside
 * the venue hours, a price for every bookable interval, and an active venue without a suspension. Every gap of the
 * price coverage is one missing item of the one 422; there is no separate PRICE_COVERAGE_INCOMPLETE here.
 */
class OpenCourt
{
    private const WEEKDAYS = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ nhật'];

    public function __construct(
        private readonly OperationalStatusRepositoryInterface $statuses,
        private readonly VenueOperatingCalendar $calendar,
        private readonly PriceCoverageService $coverage,
    ) {}

    public function execute(int $courtId): Court
    {
        return DB::transaction(function () use ($courtId): Court {
            $court = $this->statuses->lockCourt($courtId) ?? abort(404, 'Sân không tồn tại.');
            LifecycleGuard::assertCommandSource((string) $court->status, Court::STATUS_INACTIVE, Court::STATUS_ACTIVE);
            $this->assertConditions($court, 'Chưa đủ điều kiện để mở nhận đặt sân.');

            $this->statuses->setStatus($court, Court::STATUS_ACTIVE);
            DB::afterCommit(function () use ($court): void {
                Cache::increment('courts_version');
                CourtStatusUpdated::dispatch((int) $court->venue_id, (int) $court->id, Court::STATUS_ACTIVE);
            });

            return $court;
        });
    }

    /** The one condition check of opening and resuming a court (D3, D4). Locks the venue after the locked court. */
    public function assertConditions(Court $court, string $message): void
    {
        $venue = $this->statuses->lockVenue((int) $court->venue_id) ?? abort(404, 'Cơ sở không tồn tại.');
        $missing = $this->missingItems($court, $venue);
        if ($missing !== []) {
            throw new OpeningConditionsUnmetException($message, $missing);
        }
    }

    /** @return list<array<string, mixed>> */
    public function missingItems(Court $court, Venue $venue): array
    {
        $items = [];
        // ponytail: courts.code arrives with task 2.9; until then a non-empty name stands in for the code.
        if (array_filter($court->getTranslations('name'), fn (mixed $name): bool => trim((string) $name) !== '') === []) {
            $items[] = ['code' => 'court_code', 'message' => 'Sân chưa có mã sân.'];
        }
        if (! in_array($court->sport_type, Court::SPORT_TYPES, true)) {
            $items[] = ['code' => 'court_sport', 'message' => 'Sân chưa chọn môn thể thao hợp lệ.'];
        }
        $hasServiceTime = $this->hasServiceTime($court);
        if (! $hasServiceTime) {
            $items[] = ['code' => 'service_schedule', 'message' => 'Sân chưa có lịch phục vụ nằm trong giờ hoạt động của cơ sở.'];
        }
        if (! $venue->isActive()) {
            $items[] = ['code' => 'venue_not_active', 'message' => 'Cơ sở của sân chưa ở trạng thái hoạt động.'];
        }
        if ($venue->isSuspended()) {
            $items[] = ['code' => 'venue_suspended', 'message' => 'Cơ sở đang bị nền tảng đình chỉ.'];
        }
        // Without a service schedule the coverage service reads the court as open all day and would list noise.
        if ($hasServiceTime) {
            foreach ($this->coverage->missingItems($court, CarbonImmutable::now()) as $gap) {
                $items[] = ['code' => 'price_coverage_gap', 'message' => self::gapMessage($gap), ...$gap];
            }
        }

        return $items;
    }

    /** The effective schedule (the court's own, else the venue's) shares at least one minute with the venue hours. */
    private function hasServiceTime(Court $court): bool
    {
        $schedule = $this->calendar->scheduleFor($court);
        $venueHours = $this->statuses->venueSchedule((int) $court->venue_id);
        // openMinutes() reads a missing schedule as open all day, so both have to exist.
        if ($schedule === null || $venueHours === null) {
            return false;
        }
        foreach (range(1, 7) as $weekday) {
            foreach (VenueOperatingCalendar::openMinutes($schedule, null, $weekday) as [$from, $to]) {
                foreach (VenueOperatingCalendar::openMinutes($venueHours, null, $weekday) as [$venueFrom, $venueTo]) {
                    if (max($from, $venueFrom) < min($to, $venueTo)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** @param  array{weekday: ?int, date: ?string, start: string, end: string}  $gap */
    private static function gapMessage(array $gap): string
    {
        $day = $gap['date'] !== null
            ? 'ngày '.Carbon::parse($gap['date'])->format('d/m/Y')
            : (self::WEEKDAYS[$gap['weekday']] ?? 'Thứ '.$gap['weekday']);

        return "Chưa có giá cho {$day} {$gap['start']}–{$gap['end']}.";
    }
}
