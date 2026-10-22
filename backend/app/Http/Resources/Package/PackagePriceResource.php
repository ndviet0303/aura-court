<?php

namespace App\Http\Resources\Package;

use App\Models\PackagePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A package price as admins and owners read it: whole VND and a UTC effective time (D3). */
class PackagePriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PackagePrice $price */
        $price = $this->resource;

        return [
            'id' => $price->id,
            'term_days' => $price->term_days->value,
            'amount' => $price->amount,
            'effective_at' => $price->effective_at->toISOString(),
        ];
    }
}
