<?php

namespace App\Http\Resources\Package;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One row of PackagePriceCatalog::termsAt: a term's days, its applicable price and its next scheduled price. */
class PackageTermPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array{term: PackageTerm, current: ?PackagePrice, next: ?PackagePrice} $row */
        $row = $this->resource;

        return [
            'term_days' => $row['term']->value,
            'days' => $row['term']->value,
            'current_price' => $row['current'] ? PackagePriceResource::make($row['current']) : null,
            'next_price' => $row['next'] ? PackagePriceResource::make($row['next']) : null,
        ];
    }
}
