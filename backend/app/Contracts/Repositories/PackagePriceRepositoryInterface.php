<?php

namespace App\Contracts\Repositories;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePrice;
use Carbon\CarbonInterface;

interface PackagePriceRepositoryInterface
{
    /** D3: among the term's prices not replaced by $at and effective at or before it, the latest effective_at, then the largest id. */
    public function findApplicable(PackageTerm $term, CarbonInterface $at): ?PackagePrice;

    /** The term's unreplaced price that takes effect after $at: the earliest effective_at, then the largest id. */
    public function findNextScheduled(PackageTerm $term, CarbonInterface $at): ?PackagePrice;

    /** D3: serializes price writes for the term until the current transaction ends. */
    public function lockTerm(PackageTerm $term): void;

    /** Sets replaced_at = $now on the term's unreplaced prices whose effective_at is after $now. */
    public function replaceScheduled(PackageTerm $term, CarbonInterface $now): int;

    public function create(array $attributes): PackagePrice;
}
