<?php

namespace App\Domain\Package\DTOs;

use App\Http\Requests\Partner\TerminateVenuePackageRequest;

readonly class TerminateVenuePackageData
{
    public function __construct(
        public int $organizationId,
        public int $venueId,
        public bool $consequencesConfirmed,
        public int $terminatedBy,
    ) {}

    public static function fromRequest(TerminateVenuePackageRequest $request, int $organizationId, int $venueId): self
    {
        return new self(
            organizationId: $organizationId,
            venueId: $venueId,
            consequencesConfirmed: $request->boolean('confirm_consequences'),
            terminatedBy: (int) $request->user()->id,
        );
    }
}
