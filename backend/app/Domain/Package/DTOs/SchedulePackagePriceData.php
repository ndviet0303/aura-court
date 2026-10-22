<?php

namespace App\Domain\Package\DTOs;

use App\Domain\Package\Enums\PackageTerm;
use App\Http\Requests\Admin\StorePackagePriceRequest;
use Carbon\Carbon;
use Carbon\CarbonInterface;

readonly class SchedulePackagePriceData
{
    public function __construct(
        public PackageTerm $term,
        public int $amount,
        public ?CarbonInterface $effectiveAt,
        public int $createdBy,
    ) {}

    public static function fromRequest(StorePackagePriceRequest $request): self
    {
        $effectiveAt = $request->validated('effective_at');

        return new self(
            term: PackageTerm::from((int) $request->validated('term_days')),
            amount: (int) $request->validated('amount'),
            // A value without an offset is read in the app timezone (VN wall time).
            effectiveAt: $effectiveAt ? Carbon::parse($effectiveAt)->utc() : null,
            createdBy: (int) $request->user()->id,
        );
    }
}
