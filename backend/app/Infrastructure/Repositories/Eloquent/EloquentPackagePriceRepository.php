<?php

namespace App\Infrastructure\Repositories\Eloquent;

use App\Contracts\Repositories\PackagePriceRepositoryInterface;
use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePrice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EloquentPackagePriceRepository implements PackagePriceRepositoryInterface
{
    public function findApplicable(PackageTerm $term, CarbonInterface $at): ?PackagePrice
    {
        // The columns hold UTC wall time and the grammar binds a Carbon without converting it.
        $at = $at->copy()->utc();

        return PackagePrice::query()
            ->where('term_days', $term->value)
            ->where('effective_at', '<=', $at)
            ->where(fn ($query) => $query->whereNull('replaced_at')->orWhere('replaced_at', '>', $at))
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    public function findNextScheduled(PackageTerm $term, CarbonInterface $at): ?PackagePrice
    {
        return PackagePrice::query()
            ->where('term_days', $term->value)
            ->where('effective_at', '>', $at->copy()->utc())
            ->whereNull('replaced_at')
            ->orderBy('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    public function lockTerm(PackageTerm $term): void
    {
        // 5003 namespaces module 05 package-price locks; the lock also covers a term's first price.
        DB::select('select pg_advisory_xact_lock(?, ?)', [5003, $term->value]);
    }

    public function replaceScheduled(PackageTerm $term, CarbonInterface $now): int
    {
        $now = $now->copy()->utc();

        return PackagePrice::query()
            ->where('term_days', $term->value)
            ->where('effective_at', '>', $now)
            ->whereNull('replaced_at')
            ->update(['replaced_at' => $now]);
    }

    public function create(array $attributes): PackagePrice
    {
        return PackagePrice::query()->create($attributes);
    }
}
