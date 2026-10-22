<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\PlatformFeeReportRepositoryInterface;
use App\Domain\Package\DTOs\PlatformFeeReportFilterData;
use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePaymentRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentPlatformFeeReportRepository implements PlatformFeeReportRepositoryInterface
{
    public function report(PlatformFeeReportFilterData $filter): array
    {
        $rows = PackagePaymentRequest::query()
            ->with('venue')
            ->selectRaw(
                'venue_id, term_days, sum(amount) as amount, count(*) filter (where kind = ?) as purchases, count(*) filter (where kind = ?) as renewals',
                [PackagePaymentRequest::KIND_PURCHASE, PackagePaymentRequest::KIND_RENEWAL],
            )
            ->where('status', PackagePaymentRequest::STATUS_PAID)
            // paid_at holds UTC wall time and the grammar binds a Carbon without converting it.
            ->where('paid_at', '>=', $filter->paidFrom)
            ->where('paid_at', '<', $filter->paidBefore)
            ->when($filter->venueId, fn (Builder $query, int $venueId) => $query->where('venue_id', $venueId))
            ->when($filter->term, fn (Builder $query, PackageTerm $term) => $query->where('term_days', $term->value))
            ->groupBy('venue_id', 'term_days')
            ->orderBy('venue_id')
            ->orderBy('term_days')
            ->get()
            ->map(fn (PackagePaymentRequest $row) => [
                'venue_id' => $row->venue_id,
                'venue_name' => $row->venue?->name,
                'term_days' => $row->term_days->value,
                'amount' => $row->amount,
                'purchases' => (int) $row->getAttribute('purchases'),
                'renewals' => (int) $row->getAttribute('renewals'),
            ]);

        return [
            'totals' => $this->sum($rows),
            'by_term' => array_map(
                fn (PackageTerm $term) => ['term_days' => $term->value, ...$this->sum($rows->where('term_days', $term->value))],
                PackageTerm::cases(),
            ),
            'by_venue' => $rows->values()->all(),
        ];
    }

    /** @return array{amount: int, purchases: int, renewals: int} */
    private function sum(Collection $rows): array
    {
        return [
            'amount' => (int) $rows->sum('amount'),
            'purchases' => (int) $rows->sum('purchases'),
            'renewals' => (int) $rows->sum('renewals'),
        ];
    }
}
