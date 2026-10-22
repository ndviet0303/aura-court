<?php

namespace App\Domain\Promotion\DTOs;

use App\Http\Requests\Promotion\StorePromotionProgramRequest;
use App\Models\Venue;

/** A new program of a venue (design D7, D8); $terms are columns as ValidatesPromotionTerms::validatedTerms() gives them. */
readonly class CreatePromotionProgramData
{
    /**
     * @param  array<string, mixed>  $terms
     * @param  list<int>  $courtIds
     */
    public function __construct(
        public int $venueId,
        public string $type,
        public string $discountKind,
        public array $terms,
        public array $courtIds,
        public ?int $codeCount,
    ) {}

    public static function from(StorePromotionProgramRequest $request): self
    {
        /** @var Venue $venue */
        $venue = $request->route('venue');
        $codeCount = $request->validated('code_count');

        return new self(
            venueId: (int) $venue->id,
            type: (string) $request->validated('type'),
            discountKind: (string) $request->validated('discount_kind'),
            terms: $request->validatedTerms(),
            courtIds: $request->validatedCourtIds() ?? [],
            codeCount: $codeCount === null ? null : (int) $codeCount,
        );
    }
}
