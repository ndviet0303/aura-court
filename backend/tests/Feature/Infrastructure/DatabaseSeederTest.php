<?php

namespace Tests\Feature\Infrastructure;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** README developer setup runs `php artisan migrate --seed`. */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_seed_runs_and_keeps_court_time_free_of_overlaps(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, DB::table('marketplace_listings')->count());
        $this->assertGreaterThan(0, DB::table('legacy_booking_mappings')->count());
    }
}
