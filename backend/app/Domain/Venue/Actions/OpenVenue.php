<?php

namespace App\Domain\Venue\Actions;

use App\Contracts\Repositories\OperationalStatusRepositoryInterface;
use App\Domain\Venue\Rules\LifecycleGuard;
use App\Domain\Venue\Rules\VenueInformationRules;
use App\Exceptions\OpeningConditionsUnmetException;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;
use App\Services\Reservation\VenueOperatingCalendar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * D1: the owner opens an inactive venue once its BR-26 information is valid, it has weekly hours with an open
 * interval and no active suspension. Plan, partner approval, collection connection, policy, courts and prices
 * belong to booking eligibility, not to opening. Courts keep their own statuses.
 */
class OpenVenue
{
    public function __construct(private readonly OperationalStatusRepositoryInterface $statuses) {}

    public function execute(int $venueId): Venue
    {
        return DB::transaction(function () use ($venueId): Venue {
            $venue = $this->statuses->lockVenue($venueId) ?? abort(404, 'Cơ sở không tồn tại.');
            LifecycleGuard::assertCommandSource((string) $venue->status, Venue::STATUS_INACTIVE, Venue::STATUS_ACTIVE);
            $this->assertConditions($venue, 'Chưa đủ điều kiện để mở hoạt động cơ sở.');

            $this->statuses->setStatus($venue, Venue::STATUS_ACTIVE);
            DB::afterCommit(fn () => Cache::increment('venues_version'));

            return $venue;
        });
    }

    /** The one condition check of opening and resuming a venue (D1, D4). */
    public function assertConditions(Venue $venue, string $message): void
    {
        $missing = $this->missingItems($venue);
        if ($missing !== []) {
            throw new OpeningConditionsUnmetException($message, $missing);
        }
    }

    /** @return list<array<string, mixed>> */
    public function missingItems(Venue $venue): array
    {
        $information = Validator::make(VenueInformationRules::trim([
            'name' => $venue->name,
            'address' => $venue->address,
            'phone' => $venue->phone,
            'email' => $venue->email,
            'description' => $venue->description,
            'amenities' => $venue->amenities,
        ]), VenueInformationRules::rules(), VenueInformationRules::messages());

        $items = [];
        foreach ($information->errors()->messages() as $field => $messages) {
            $items[] = ['code' => 'venue_information', 'field' => $field, 'message' => $messages[0]];
        }
        if (! self::hasOpenInterval($this->statuses->venueSchedule((int) $venue->id))) {
            $items[] = ['code' => 'operating_hours', 'message' => 'Cơ sở chưa có giờ hoạt động với ít nhất một khung giờ mở cửa.'];
        }
        if ($venue->isSuspended()) {
            $items[] = ['code' => 'venue_suspended', 'message' => 'Cơ sở đang bị nền tảng đình chỉ.'];
        }

        return $items;
    }

    private static function hasOpenInterval(?VenueOperatingSchedule $schedule): bool
    {
        foreach (range(1, 7) as $weekday) {
            foreach (VenueOperatingCalendar::openMinutes($schedule, null, $weekday) as [$from, $to]) {
                if ($from < $to) {
                    return true;
                }
            }
        }

        return false;
    }
}
