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

    public function test_full_seed_runs_without_court_overlaps_or_small_hours_activity(): void
    {
        // A morning seed, when history dated "hours ago" falls in the night most often.
        $this->travelTo(now()->setTime(7, 30));
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(0, DB::table('marketplace_listings')->count());
        $this->assertGreaterThan(0, DB::table('legacy_booking_mappings')->count());

        // Before 06:00 only the nightly jobs (reconciliation, point expiry) run and staff check in players for 06:00 courts.
        $night = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'created_at')
            ->where('data_type', 'like', 'timestamp%')
            ->whereNotIn('table_name', ['reconciliation_runs', 'point_ledgers', 'booking_attendances'])
            ->pluck('table_name')
            ->filter(fn (string $table) => DB::table($table)->whereRaw('extract(hour from created_at) < 6')->exists());
        $this->assertSame([], $night->values()->all());
    }
}
