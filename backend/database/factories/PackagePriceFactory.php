<?php

namespace Database\Factories;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackagePrice>
 */
class PackagePriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term_days' => PackageTerm::Days30,
            'amount' => 300000,
            'effective_at' => now()->utc(),
            'created_by' => User::factory(),
        ];
    }
}
