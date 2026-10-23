<?php

namespace App\Http\Resources\Customer;

use App\Domain\Customer\Rules\CustomerTier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** FR-110: a customer row of VenueCustomerRepositoryInterface with its BR-141 tier and BR-142 usage rate. */
class VenueCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $completed = (int) $this->resource->completed;
        $noShow = (int) $this->resource->no_show;

        return [
            'id' => (int) $this->resource->id,
            'name' => $this->resource->name,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'avatar_url' => $this->resource->avatar_url,
            'completed' => $completed,
            'no_show' => $noShow,
            'used_minutes' => (int) $this->resource->used_minutes,
            'completed_amount' => (int) $this->resource->completed_amount,
            'last_used_at' => $this->resource->last_used_at ? Carbon::parse($this->resource->last_used_at, config('app.timezone'))->toIso8601String() : null,
            'tier' => CustomerTier::for($completed),
            'usage_rate' => CustomerTier::usageRate($completed, $noShow),
        ];
    }
}
