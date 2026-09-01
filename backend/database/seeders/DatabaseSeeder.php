<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CouponSeeder::class,
            OrganizationVenueSeeder::class,
            UserSeeder::class,
            SportZoneCourtSeeder::class,
            CourtBlockSeeder::class,
            BookingPaymentSeeder::class,
            PosProductSeeder::class,
            MembershipPlanSeeder::class,
            TournamentSeeder::class,
            MatchmakingSeeder::class,
            HostKycSeeder::class,
            DisputeFinanceSeeder::class,
            MarketplaceSeeder::class,
        ]);
    }
}
