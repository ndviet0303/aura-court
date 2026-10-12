<?php

namespace Tests\Feature\Infrastructure;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OrganizationVenueSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
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

    public function test_seeding_outside_local_and_testing_takes_passwords_only_from_env(): void
    {
        $keys = ['SEED_ADMIN_PASSWORD', 'SEED_DEMO_ADMIN_PASSWORD', 'SEED_DEMO_PASSWORD'];
        $saved = Arr::only($_SERVER, $keys);
        // UserSeeder attaches the accounts to these organizations and venues.
        $this->seed(OrganizationVenueSeeder::class);
        $this->app['env'] = 'staging';

        try {
            // $_SERVER is read first by env(), so this overrides any developer .env value.
            foreach ($keys as $key) {
                $_SERVER[$key] = '';
            }

            $this->assertThrows(fn () => $this->seed(UserSeeder::class), RuntimeException::class, 'SEED_DEMO_PASSWORD is not set');
            $this->assertSame(0, User::count());

            $passwords = [];
            foreach ($keys as $key) {
                $_SERVER[$key] = $passwords[$key] = Str::password();
            }

            $this->seed(UserSeeder::class);

            $this->assertTrue(Hash::check($passwords['SEED_ADMIN_PASSWORD'], User::where('email', 'admin@auracourt.vn')->value('password')));
            $this->assertTrue(Hash::check($passwords['SEED_DEMO_PASSWORD'], User::where('email', 'user@example.com')->value('password')));
        } finally {
            foreach ($keys as $key) {
                unset($_SERVER[$key]);
            }
            $_SERVER += $saved;
        }
    }
}
