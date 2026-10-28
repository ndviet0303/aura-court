<?php

namespace App\Domain\Promotion\DTOs;

use App\Http\Requests\Promotion\UpdatePromotionProgramRequest;

/** The terms sent to change an issued program, as columns; a court set only when one was sent. */
readonly class UpdatePromotionProgramData
{
    /**
     * @param  array<string, mixed>  $terms
     * @param  list<int>|null  $courtIds
     */
    public function __construct(
        public int $programId,
        public array $terms,
        public ?array $courtIds,
        public string $discountKind,
    ) {}

    public static function from(UpdatePromotionProgramRequest $request): self
    {
        $program = $request->program();

        return new self(
            programId: (int) $program->id,
            terms: $request->validatedTerms(),
            courtIds: $request->validatedCourtIds(),
            discountKind: $request->discountKind(),
        );
    }
}
