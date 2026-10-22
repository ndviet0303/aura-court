<?php

namespace Database\Factories;

use App\Domain\Package\Enums\PackageTerm;
use App\Models\Venue;
use App\Models\VenuePackage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VenuePackage>
 */
class VenuePackageFactory extends Factory
{
    /**
     * An active 360-day package from now; ends_at follows any overridden term_days or starts_at.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory()->withoutPackage(),
            'organization_id' => fn (array $attributes) => Venue::query()->findOrFail($attributes['venue_id'])->organization_id,
            'term_days' => PackageTerm::Days360,
            'starts_at' => now()->utc(),
            'ends_at' => function (array $attributes) {
                $term = $attributes['term_days'];

                return ($term instanceof PackageTerm ? $term : PackageTerm::from($term))->endsAt(Carbon::parse($attributes['starts_at']));
            },
        ];
    }
}
