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
            // Package prices need the platform admin from UserSeeder as their author.
            VenuePackageSeeder::class,
            HostKycSeeder::class,
            CourtSeeder::class,
            PromotionProgramSeeder::class,
            OperationalStopSeeder::class,
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
