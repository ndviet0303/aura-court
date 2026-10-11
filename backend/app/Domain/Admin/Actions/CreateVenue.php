<?php

namespace App\Domain\Admin\Actions;

use App\Contracts\Repositories\VenueRepositoryInterface;
use App\Domain\Subscription\Actions\EnforcePlanQuota;
use App\Models\Organization;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateVenue
{
    public function __construct(
        private readonly VenueRepositoryInterface $venues,
    ) {}

    public function execute(array $data): Venue
    {
        return DB::transaction(function () use ($data) {
            $slug = $this->generateUniqueSlug($data['name']);
            if (! isset($data['status'])) {
                $data['status'] = 'published';
            }
            if (! isset($data['organization_id'])) {
                $user = request()?->user();
                $org = $user?->organizations()->where('organizations.status', 'active')->first()
                    ?? Organization::where('status', 'active')->where('partner_status', 'approved')->first()
                    ?? Organization::where('status', 'active')->first()
                    ?? Organization::first();
                if ($org) {
                    $data['organization_id'] = $org->id;
                    if ($org->partner_status !== 'approved') {
                        $org->update(['partner_status' => 'approved']);
                    }
                }
            }

            if (isset($data['organization_id'])) {
                $org = Organization::find($data['organization_id']);
                if ($org && $org->hasActiveSubscription()) {
                    app(EnforcePlanQuota::class)->execute($org->id, 'venues', 1);
                }
            }

            $venue = $this->venues->create($data + ['slug' => $slug]);

            // Automatically create a default sport zone for the new venue
            $venue->sportZones()->create([
                'name' => 'Khu Cầu Lông',
                'sport_type' => 'badminton',
                'slot_duration' => 60,
                'is_active' => true,
            ]);

            return $venue->load('sportZones');
        });
    }

    private function generateUniqueSlug(string|array $name): string
    {
        $rawName = is_array($name) ? ($name['vi'] ?? $name['en'] ?? reset($name) ?: 'venue') : $name;
        $base = Str::slug((string) $rawName) ?: 'venue';
        $slug = $base;
        $suffix = 2;
        while ($this->venues->existsBySlug($slug)) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
