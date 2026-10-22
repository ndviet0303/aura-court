<?php

namespace App\Domain\Package\DTOs;

use App\Domain\Package\Enums\PackageTerm;
use App\Http\Requests\Partner\StorePackagePaymentRequest;

readonly class CreatePackagePaymentRequestData
{
    public function __construct(
        public int $organizationId,
        public int $venueId,
        public PackageTerm $term,
        public int $createdBy,
    ) {}

    public static function fromRequest(StorePackagePaymentRequest $request, int $organizationId, int $venueId): self
    {
        return new self(
            organizationId: $organizationId,
            venueId: $venueId,
            term: PackageTerm::from((int) $request->validated('term_days')),
            createdBy: (int) $request->user()->id,
        );
    }
}
