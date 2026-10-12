<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            OrganizationVenueSeeder::class,
            UserSeeder::class,
            HostKycSeeder::class,
            SportZoneCourtSeeder::class,
            CouponSeeder::class,
            CourtBlockSeeder::class,
            BookingPaymentSeeder::class,
            PosProductSeeder::class,
            CashShiftSeeder::class,
            PosSalesSeeder::class,
            MembershipPlanSeeder::class,
            TournamentSeeder::class,
            MatchmakingSeeder::class,
            CommunitySeeder::class,
            DisputeFinanceSeeder::class,
            MarketplaceSeeder::class,
            RecurringSeriesSeeder::class,
        ]);
    }
}
