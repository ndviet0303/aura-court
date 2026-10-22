<?php

namespace Database\Seeders;

use App\Domain\Package\Enums\PackageStatus;
use App\Domain\Package\Enums\PackageTerm;
use App\Models\PackagePrice;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePackage;
use Illuminate\Database\Seeder;

/** A price for each package term and an active 360-day package for every venue, so seeded venues take bookings (D9). */
class VenuePackageSeeder extends Seeder
{
    private const PRICES = [30 => 300000, 90 => 810000, 180 => 1500000, 360 => 2700000];

    public function run(): void
    {
        $admin = User::where('email', 'admin@auracourt.vn')->firstOrFail();
        foreach (self::PRICES as $termDays => $amount) {
            // A term that already has a price keeps it, so a reseed never overrides a price the admin set.
            PackagePrice::firstOrCreate(['term_days' => $termDays], ['amount' => $amount, 'effective_at' => now(), 'created_by' => $admin->id]);
        }

        foreach (Venue::orderBy('id')->get() as $venue) {
            $current = VenuePackage::where('venue_id', $venue->id)->latest('id')->first();
            if ($current?->statusAt(now()) === PackageStatus::Active) {
                continue;
            }
            VenuePackage::create([
                'organization_id' => $venue->organization_id,
                'venue_id' => $venue->id,
                'term_days' => PackageTerm::Days360,
                'starts_at' => now(),
                'ends_at' => PackageTerm::Days360->endsAt(now()),
            ]);
        }
    }
}
