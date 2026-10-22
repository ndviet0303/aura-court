<?php

namespace App\Services\Package;

use App\Contracts\Repositories\PackagePriceRepositoryInterface;
use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePrice;
use Carbon\CarbonInterface;

/** D3 price reads shared by the admin price table, package requests (3.1) and the owner package view (3.6). */
class PackagePriceCatalog
{
    public function __construct(private readonly PackagePriceRepositoryInterface $prices) {}

    public function applicableAt(PackageTerm $term, CarbonInterface $at): ?PackagePrice
    {
        return $this->prices->findApplicable($term, $at);
    }

    /** @return list<array{term: PackageTerm, current: ?PackagePrice, next: ?PackagePrice}> */
    public function termsAt(CarbonInterface $at): array
    {
        return array_map(fn (PackageTerm $term) => [
            'term' => $term,
            'current' => $this->prices->findApplicable($term, $at),
            'next' => $this->prices->findNextScheduled($term, $at),
        ], PackageTerm::cases());
    }
}
