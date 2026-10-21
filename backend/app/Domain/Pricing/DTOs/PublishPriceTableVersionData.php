<?php

namespace App\Domain\Pricing\DTOs;

use App\Http\Requests\Pricing\PublishPriceTableVersionRequest;
use Carbon\CarbonImmutable;

/** A new version of a price table (design D1); no effective time means at the publication, as a past one does. */
readonly class PublishPriceTableVersionData
{
    /**
     * @param  list<array{weekdays: ?list<int>, dates: ?list<string>, start_minute: int, end_minute: int, price_per_hour: int}>  $intervals
     */
    public function __construct(
        public int $priceTableId,
        public int $organizationId,
        public ?CarbonImmutable $effectiveAt,
        public array $intervals,
    ) {}

    public static function from(PublishPriceTableVersionRequest $request): self
    {
        $table = $request->table()->loadMissing('venue');
        $effectiveAt = $request->validated('effective_at');

        return new self(
            priceTableId: (int) $table->id,
            organizationId: (int) $table->venue->organization_id,
            effectiveAt: $effectiveAt === null ? null : CarbonImmutable::parse($effectiveAt),
            intervals: $request->validatedIntervals(),
        );
    }
}
