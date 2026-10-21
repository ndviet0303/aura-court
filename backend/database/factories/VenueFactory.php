<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Venue;
use App\Models\VenuePackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => fn () => Organization::create([
                'name' => fake()->company(),
                'slug' => 'org-'.Str::lower(Str::random(10)),
                'status' => 'active',
                'partner_status' => 'approved',
                'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
            ])->id,
            'name' => fake()->company(),
            'slug' => 'venue-'.Str::lower(Str::random(10)),
            'address' => fake()->streetAddress(),
            'status' => Venue::STATUS_ACTIVE,
        ];
    }

    /** D9: a venue takes new bookings by default, so it starts with an active 360-day package. */
    public function configure(): static
    {
        return $this->afterCreating(fn (Venue $venue) => VenuePackage::factory()->create([
            'venue_id' => $venue->id,
            'organization_id' => $venue->organization_id,
        ]));
    }

    // ponytail: also drops afterCreating callbacks chained before it; call withoutPackage() first.
    public function withoutPackage(): static
    {
        return $this->withoutAfterCreating();
    }
}
