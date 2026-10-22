<?php

namespace Database\Factories;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePaymentRequest;
use App\Models\PackagePrice;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PackagePaymentRequest>
 */
class PackagePaymentRequestFactory extends Factory
{
    /**
     * A pending 30-day purchase that expires in 10 minutes, priced by its own price row.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_code' => 'PKR'.Str::upper(Str::random(8)),
            'payment_code' => 'PKG'.Str::upper(Str::random(8)),
            'venue_id' => Venue::factory()->withoutPackage(),
            'organization_id' => fn (array $attributes) => Venue::query()->findOrFail($attributes['venue_id'])->organization_id,
            'kind' => PackagePaymentRequest::KIND_PURCHASE,
            'term_days' => PackageTerm::Days30,
            'amount' => 300000,
            'package_price_id' => fn (array $attributes) => PackagePrice::factory()->state([
                'term_days' => $attributes['term_days'],
                'amount' => $attributes['amount'],
            ]),
            'expires_at' => now()->utc()->addMinutes(10),
            'status' => PackagePaymentRequest::STATUS_PENDING,
            'created_by' => User::factory(),
        ];
    }
}
