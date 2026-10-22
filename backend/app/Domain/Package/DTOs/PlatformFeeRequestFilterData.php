<?php

namespace App\Domain\Package\DTOs;

use App\Domain\Package\Enums\PackageTerm;
use App\Http\Requests\Admin\ListPlatformFeeRequestsRequest;
use Carbon\CarbonImmutable;

/** D15: the admin's payment request filters. The period is Vietnam calendar days of creation, [from 00:00, to + 1 day 00:00). */
readonly class PlatformFeeRequestFilterData
{
    public function __construct(
        public ?string $status,
        public ?int $venueId,
        public ?PackageTerm $term,
        public ?CarbonImmutable $createdFrom,
        public ?CarbonImmutable $createdBefore,
        public int $perPage = 30,
    ) {}

    public static function fromRequest(ListPlatformFeeRequestsRequest $request): self
    {
        $venueId = $request->validated('venue_id');
        $term = $request->validated('term');
        $from = $request->validated('from');
        // created_at holds app-timezone wall time (default cast), and the grammar binds a Carbon without converting it.
        $day = fn (string $date) => CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Ho_Chi_Minh');

        return new self(
            status: $request->validated('status'),
            venueId: $venueId === null ? null : (int) $venueId,
            term: $term === null ? null : PackageTerm::from((int) $term),
            createdFrom: $from === null ? null : $day($from)->setTimezone(config('app.timezone')),
            createdBefore: $from === null ? null : $day($request->validated('to'))->addDay()->setTimezone(config('app.timezone')),
            perPage: (int) $request->validated('per_page', 30),
        );
    }
}
