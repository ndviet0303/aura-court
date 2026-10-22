<?php

namespace App\Domain\Package\DTOs;

use App\Domain\Package\Enums\PackageStatus;
use App\Http\Requests\Admin\ListPlatformFeeVenuesRequest;

/** D15: the admin's venue package filters; expiringWithin is the 7, 3 or 1-day group of BR-116. */
readonly class PlatformFeeVenueFilterData
{
    public function __construct(
        public ?PackageStatus $status,
        public ?int $expiringWithin,
        public ?string $search,
        public int $perPage = 30,
    ) {}

    public static function fromRequest(ListPlatformFeeVenuesRequest $request): self
    {
        $status = $request->validated('status');
        $expiringWithin = $request->validated('expiring_within');
        $search = trim((string) $request->validated('search'));

        return new self(
            status: $status === null ? null : PackageStatus::from($status),
            expiringWithin: $expiringWithin === null ? null : (int) $expiringWithin,
            search: $search === '' ? null : $search,
            perPage: (int) $request->validated('per_page', 30),
        );
    }
}
