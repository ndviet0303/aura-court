<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\PlatformFeeRequestRepositoryInterface;
use App\Domain\Package\DTOs\PlatformFeeRequestFilterData;
use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePaymentRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EloquentPlatformFeeRequestRepository implements PlatformFeeRequestRepositoryInterface
{
    public function paginate(PlatformFeeRequestFilterData $filter, CarbonInterface $now): LengthAwarePaginator
    {
        return $this->filtered($filter, $now)->paginate($filter->perPage);
    }

    public function all(PlatformFeeRequestFilterData $filter, CarbonInterface $now): Collection
    {
        return $this->filtered($filter, $now)->get();
    }

    /** @return Builder<PackagePaymentRequest> */
    private function filtered(PlatformFeeRequestFilterData $filter, CarbonInterface $now): Builder
    {
        return PackagePaymentRequest::query()
            ->with(['venue', 'organization.owner'])
            ->when($filter->status, fn (Builder $query, string $status) => $this->whereStatusAt($query, $status, $now))
            ->when($filter->venueId, fn (Builder $query, int $venueId) => $query->where('venue_id', $venueId))
            ->when($filter->term, fn (Builder $query, PackageTerm $term) => $query->where('term_days', $term->value))
            ->when($filter->createdFrom, fn (Builder $query, CarbonImmutable $from) => $query
                ->where('created_at', '>=', $from)
                ->where('created_at', '<', $filter->createdBefore))
            ->orderByDesc('id');
    }

    public function findByCode(string $requestCode): ?PackagePaymentRequest
    {
        return PackagePaymentRequest::query()
            ->with(['venue', 'organization.owner', 'receipts' => fn ($query) => $query->orderBy('transferred_at')->orderBy('id')])
            ->where('request_code', $requestCode)
            ->first();
    }

    /** @param Builder<PackagePaymentRequest> $query */
    private function whereStatusAt(Builder $query, string $status, CarbonInterface $now): void
    {
        // expires_at holds UTC wall time and the grammar binds a Carbon without converting it.
        $at = $now->copy()->utc();

        match ($status) {
            PackagePaymentRequest::STATUS_PENDING => $query->where('status', PackagePaymentRequest::STATUS_PENDING)->where('expires_at', '>', $at),
            PackagePaymentRequest::STATUS_EXPIRED => $query->where(fn (Builder $expired) => $expired
                ->where('status', PackagePaymentRequest::STATUS_EXPIRED)
                ->orWhere(fn (Builder $overdue) => $overdue->where('status', PackagePaymentRequest::STATUS_PENDING)->where('expires_at', '<=', $at))),
            default => $query->where('status', $status),
        };
    }
}
