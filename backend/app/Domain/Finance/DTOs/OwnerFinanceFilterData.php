<?php

namespace App\Domain\Finance\DTOs;

use App\Http\Requests\Partner\ListOwnerFinanceTransactionsRequest;
use App\Http\Requests\Partner\ShowOwnerFinanceReportRequest;
use Carbon\CarbonImmutable;

/** D12: the owner's finance filters. The period is Vietnam calendar days, [from 00:00, to + 1 day 00:00). */
readonly class OwnerFinanceFilterData
{
    public function __construct(
        public int $organizationId,
        public ?int $venueId,
        public ?int $courtId,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public int $perPage = 30,
    ) {}

    public static function fromRequest(ListOwnerFinanceTransactionsRequest|ShowOwnerFinanceReportRequest $request, int $organizationId): self
    {
        $venueId = $request->validated('venue_id');
        $courtId = $request->validated('court_id');
        // The finance columns hold app-timezone wall time (datetime cast), and the grammar binds a Carbon without converting it.
        $day = fn (string $date) => CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Ho_Chi_Minh');

        return new self(
            organizationId: $organizationId,
            venueId: $venueId === null ? null : (int) $venueId,
            courtId: $courtId === null ? null : (int) $courtId,
            startsAt: $day($request->validated('from'))->setTimezone(config('app.timezone')),
            endsAt: $day($request->validated('to'))->addDay()->setTimezone(config('app.timezone')),
            perPage: (int) $request->validated('per_page', 30),
        );
    }
}
