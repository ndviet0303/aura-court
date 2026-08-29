<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Models\Venue;
use Illuminate\Support\Str;

class CreateVenue
{
    public function __construct(
        private readonly VenueRepositoryInterface $venues,
    ) {}

    public function execute(array $data): Venue
    {
        $slug = $this->generateUniqueSlug($data['name']);
        $venue = $this->venues->create($data + ['slug' => $slug]);

        // Automatically create a default sport zone for the new venue
        $venue->sportZones()->create([
            'name' => 'Khu Cầu Lông',
            'sport_type' => 'badminton',
            'slot_duration' => 60,
            'is_active' => true,
        ]);

        return $venue->load('sportZones');
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'venue';
        $slug = $base;
        $suffix = 2;
        while ($this->venues->existsBySlug($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
