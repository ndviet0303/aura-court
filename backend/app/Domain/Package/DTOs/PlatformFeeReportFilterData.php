<?php

namespace App\Domain\Package\DTOs;

use App\Domain\Package\Enums\PackageTerm;
use App\Http\Requests\Admin\ShowPlatformFeeReportRequest;
use Carbon\CarbonImmutable;

/** D15: the fee report filters. The period is Vietnam calendar days, [from 00:00, to + 1 day 00:00), as a UTC range for paid_at. */
readonly class PlatformFeeReportFilterData
{
    public function __construct(
        public string $from,
        public string $to,
        public CarbonImmutable $paidFrom,
        public CarbonImmutable $paidBefore,
        public ?int $venueId,
        public ?PackageTerm $term,
    ) {}

    public static function fromRequest(ShowPlatformFeeReportRequest $request): self
    {
        $from = $request->validated('from');
        $to = $request->validated('to');
        $venueId = $request->validated('venue_id');
        $term = $request->validated('term');
        // paid_at holds UTC wall time (UtcDateTime cast), and the grammar binds a Carbon without converting it.
        $day = fn (string $date) => CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Ho_Chi_Minh');

        return new self(
            from: $from,
            to: $to,
            paidFrom: $day($from)->utc(),
            paidBefore: $day($to)->addDay()->utc(),
            venueId: $venueId === null ? null : (int) $venueId,
            term: $term === null ? null : PackageTerm::from((int) $term),
        );
    }
}
