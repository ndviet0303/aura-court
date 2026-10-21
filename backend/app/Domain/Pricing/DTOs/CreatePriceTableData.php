<?php

namespace App\Domain\Pricing\DTOs;

use App\Http\Requests\Pricing\StorePriceTableRequest;
use App\Models\PriceTable;
use App\Models\Venue;
use App\Services\Reservation\VenueOperatingCalendar;
use Carbon\CarbonImmutable;

/** A new price table and the content of its first version (design D1); interval times are venue-local minutes. */
readonly class CreatePriceTableData
{
    /**
     * @param  list<array{weekdays: ?list<int>, dates: ?list<string>, start_minute: int, end_minute: int, price_per_hour: int}>  $intervals
     */
    public function __construct(
        public int $venueId,
        public int $organizationId,
        public string $name,
        public string $kind,
        public CarbonImmutable $effectiveAt,
        public array $intervals,
    ) {}

    public static function from(StorePriceTableRequest $request): self
    {
        /** @var Venue $venue */
        $venue = $request->route('venue');
        $normal = $request->validated('kind') === PriceTable::KIND_NORMAL;

        return new self(
            venueId: (int) $venue->id,
            organizationId: (int) $venue->organization_id,
            name: (string) $request->validated('name'),
            kind: (string) $request->validated('kind'),
            effectiveAt: CarbonImmutable::parse($request->validated('effective_at')),
            intervals: array_map(fn (array $interval): array => [
                'weekdays' => $normal ? collect($interval['weekdays'])->map(fn ($day): int => (int) $day)->unique()->sort()->values()->all() : null,
                'dates' => $normal ? null : collect($interval['dates'])->unique()->sort()->values()->all(),
                'start_minute' => VenueOperatingCalendar::minuteOfDay($interval['start']),
                'end_minute' => VenueOperatingCalendar::minuteOfDay($interval['end'], true),
                'price_per_hour' => (int) $interval['price_per_hour'],
            ], $request->validated('intervals')),
        );
    }
}
