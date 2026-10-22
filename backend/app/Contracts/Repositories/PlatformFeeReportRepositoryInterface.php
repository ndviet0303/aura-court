<?php

namespace App\Contracts\Repositories;

use App\Domain\Package\DTOs\PlatformFeeReportFilterData;

interface PlatformFeeReportRepositoryInterface
{
    /**
     * D15: fees collected in the period from paid requests by paid_at, using each request's own amount. A terminated
     * package keeps its fee; expired requests and anomalous or late transfers never mark a request paid, so they stay out.
     * by_term lists every term, zeros included; by_venue has one row per venue and term.
     * D14: an export reads this same result.
     *
     * @return array{
     *     totals: array{amount: int, purchases: int, renewals: int},
     *     by_term: list<array{term_days: int, amount: int, purchases: int, renewals: int}>,
     *     by_venue: list<array{venue_id: int, venue_name: ?string, term_days: int, amount: int, purchases: int, renewals: int}>
     * }
     */
    public function report(PlatformFeeReportFilterData $filter): array;
}
