<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\PlatformFeeVenueRepositoryInterface;
use App\Domain\Package\DTOs\PlatformFeeVenueFilterData;
use App\Domain\Package\Enums\PackageStatus;
use App\Models\Venue;
use App\Models\VenuePackage;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;

class EloquentPlatformFeeVenueRepository implements PlatformFeeVenueRepositoryInterface
{
    public function paginate(PlatformFeeVenueFilterData $filter, CarbonInterface $now): LengthAwarePaginator
    {
        return $this->filtered($filter, $now)->paginate($filter->perPage);
    }

    public function all(PlatformFeeVenueFilterData $filter, CarbonInterface $now): Collection
    {
        return $this->filtered($filter, $now)->get();
    }

    /** @return Builder<Venue> */
    private function filtered(PlatformFeeVenueFilterData $filter, CarbonInterface $now): Builder
    {
        // The package columns hold UTC wall time and the grammar binds a Carbon without converting it.
        $at = $now->copy()->utc();

        return Venue::query()
            ->select('venues.*')
            // D1: the venue's current package is its newest row.
            ->leftJoin('venue_packages as cur', fn (JoinClause $join) => $join
                ->on('cur.venue_id', '=', 'venues.id')
                ->whereRaw('cur.id = (select max(p.id) from venue_packages as p where p.venue_id = venues.id)'))
            ->with(['organization.owner', 'currentPackage'])
            ->when($filter->status, fn (Builder $query, PackageStatus $status) => match ($status) {
                PackageStatus::None => $query->whereNull('cur.id'),
                PackageStatus::Terminated => $query->whereNotNull('cur.terminated_at'),
                PackageStatus::Active => $query->whereNotNull('cur.id')->whereNull('cur.terminated_at')
                    ->where('cur.starts_at', '<=', $at)->where('cur.ends_at', '>', $at),
                PackageStatus::Expired => $query->whereNotNull('cur.id')->whereNull('cur.terminated_at')
                    ->where(fn (Builder $outside) => $outside->where('cur.starts_at', '>', $at)->orWhere('cur.ends_at', '<=', $at)),
            })
            ->when($filter->expiringWithin, fn (Builder $query, int $days) => $query
                ->whereNull('cur.terminated_at')
                ->where('cur.ends_at', '>', $at)
                ->where('cur.ends_at', '<=', $at->copy()->addDays($days)))
            // name is a translations object, so each translation is matched rather than the JSON text.
            ->when($filter->search, fn (Builder $query, string $search) => $query->whereRaw(
                'exists (select 1 from json_each_text(venues.name) as translation where translation.value ilike ?)',
                ['%'.addcslashes($search, '%_\\').'%'],
            ))
            ->orderByRaw('cur.ends_at asc nulls last')
            ->orderBy('venues.id');
    }

    public function expiringCounts(CarbonInterface $now): array
    {
        $at = $now->copy()->utc();
        $counts = VenuePackage::query()
            ->selectRaw(
                'count(distinct venue_id) filter (where ends_at <= ?) as within_7, '
                .'count(distinct venue_id) filter (where ends_at <= ?) as within_3, '
                .'count(distinct venue_id) filter (where ends_at <= ?) as within_1',
                [$at->copy()->addDays(7), $at->copy()->addDays(3), $at->copy()->addDays(1)],
            )
            ->whereNull('terminated_at')
            ->where('ends_at', '>', $at)
            // D1: a row that a newer purchase of the venue superseded is no longer its package.
            ->whereNotExists(fn ($query) => $query->from('venue_packages as newer')
                ->whereColumn('newer.venue_id', 'venue_packages.venue_id')
                ->whereColumn('newer.id', '>', 'venue_packages.id'))
            ->toBase()
            ->first();

        return [7 => (int) $counts->within_7, 3 => (int) $counts->within_3, 1 => (int) $counts->within_1];
    }

    public function findWithHistory(int $venueId): ?Venue
    {
        return Venue::query()
            ->with([
                'organization.owner',
                'currentPackage',
                'packages' => fn ($query) => $query->orderByDesc('id'),
                'packagePaymentRequests' => fn ($query) => $query->with(['venue', 'organization.owner'])->orderByDesc('id'),
            ])
            ->find($venueId);
    }
}
