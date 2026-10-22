<?php

namespace App\Domain\Pricing\DTOs;

use App\Http\Requests\Pricing\StorePriceTableRequest;
use App\Models\Venue;
use Carbon\CarbonImmutable;

/** A new price table and the content of its first version (design D1); interval times are venue-local minutes. */
readonly class CreatePriceTableData
{
    /**
     * @param  list<array{weekdays: ?list<int>, dates: ?list<string>, start_minute: int, end_minute: int, price_per_hour: int}>  $intervals
     */
    public function __construct(
        public int $venueId,
        public string $name,
        public string $kind,
        public CarbonImmutable $effectiveAt,
        public array $intervals,
    ) {}

    public static function from(StorePriceTableRequest $request): self
    {
        /** @var Venue $venue */
        $venue = $request->route('venue');

        return new self(
            venueId: (int) $venue->id,
            name: (string) $request->validated('name'),
            kind: (string) $request->validated('kind'),
            effectiveAt: CarbonImmutable::parse($request->validated('effective_at')),
            intervals: $request->validatedIntervals(),
        );
    }
}
