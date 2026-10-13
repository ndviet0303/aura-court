<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Court;
use App\Models\OwnerPaymentAttempt;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\VenueBookingPolicy;
use App\Services\Reservation\CourtPriceRuleService;
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

    /** Fake values only: the real demo account and webhook key never reach tests. */
    private const RECEIVER = [
        'SEED_DEMO_RECEIVER_BANK' => 'TestBank',
        'SEED_DEMO_RECEIVER_ACCOUNT' => '9704001234567',
        'SEED_DEMO_RECEIVER_NAME' => 'TEST RECEIVER',
        'SEED_DEMO_RECEIVER_WEBHOOK_KEY' => 'test_webhook_key_0123456789abcdefghij',
    ];

    private const PASSWORDS = ['SEED_ADMIN_PASSWORD', 'SEED_DEMO_ADMIN_PASSWORD', 'SEED_DEMO_PASSWORD'];

    public function test_full_seed_mirrors_live_paths_without_court_overlaps_or_small_hours_activity(): void
    {
        // A morning seed, when history dated "hours ago" falls in the night most often. The venues migration stamps
        // aura-court with the real clock, so the seeders pin venue and zone dates to keep this independent of it.
        $this->travelTo(now()->setTime(7, 30));
        $this->withEnv(self::RECEIVER, fn () => $this->seed(DatabaseSeeder::class));

        $this->assertGreaterThan(0, DB::table('marketplace_listings')->count());
        $this->assertGreaterThan(0, DB::table('legacy_booking_mappings')->count());

        // Before 06:00 only the nightly point expiry runs and staff check in players for 06:00 courts.
        $night = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'created_at')
            ->where('data_type', 'like', 'timestamp%')
            ->whereNotIn('table_name', ['point_ledgers', 'booking_attendances'])
            ->pluck('table_name')
            ->filter(fn (string $table) => DB::table($table)->whereRaw('extract(hour from created_at) < 6')->exists());
        $this->assertSame([], $night->values()->all());

        // No wallet money, platform payment, refund or payout path.
        $this->assertSame(0, DB::table('wallet_accounts')->where('balance', '!=', 0)->count());
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        foreach (['payments', 'refunds', 'owner_refund_obligations', 'beneficiaries', 'settlements', 'payouts'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertSame(0, OwnerPaymentAttempt::where('purpose', 'booking_change')->count());
        $this->assertSame(0, DB::table('dispute_cases')->where('resolution_decision', 'resolve_and_refund')->count());

        // Paid history is what the live receipt path writes: paid attempt, allocated receipt, owner.direct_receipt journal.
        $paid = OwnerPaymentAttempt::where('status', OwnerPaymentAttempt::STATUS_PAID)->count();
        $this->assertGreaterThan(0, $paid);
        $this->assertSame($paid, OwnerPaymentAttempt::count());
        $this->assertSame(['booking', 'pos_order', 'recurring_period', 'tournament_registration'], OwnerPaymentAttempt::distinct()->orderBy('purpose')->pluck('purpose')->all());
        $this->assertSame($paid, DB::table('owner_payment_receipts')->where('status', 'allocated')->count());
        $this->assertSame($paid, DB::table('owner_payment_allocations')->count());
        $this->assertSame($paid, DB::table('journals')->where('event_type', 'owner.direct_receipt')->count());
        $this->assertSame(0, DB::table('pos_orders')->where('payment_method', 'qr')->where('payment_reference', 'not like', 'AC%')->count());

        // Every venue collects on the one configured account; only Cầu Giấy holds the key registered in SePay.
        $connections = ReceivingConnection::with('venue')->get();
        $this->assertGreaterThan(1, $connections->count());
        foreach ($connections as $connection) {
            $this->assertSame([self::RECEIVER['SEED_DEMO_RECEIVER_BANK'], self::RECEIVER['SEED_DEMO_RECEIVER_ACCOUNT'], self::RECEIVER['SEED_DEMO_RECEIVER_NAME']], [$connection->bank_code, $connection->accountNumber(), $connection->accountName()]);
            $this->assertSame($connection->venue->slug === 'aura-court', $connection->webhookSecret() === self::RECEIVER['SEED_DEMO_RECEIVER_WEBHOOK_KEY']);
        }
        $this->assertSame(0, DB::table('marketplace_listings')->where('asking_price', '>', 0)->whereNull('seller_receiver_ciphertext')->count());
        $this->assertGreaterThan(0, DB::table('entitlement_transfers')->where('status', 'completed')->where('marketplace_fee', 0)->whereJsonContains('metadata->settlement_mode', 'peer_to_peer')->count());
        $this->assertSame(0, DB::table('tournaments')->whereNull('venue_id')->count());

        // Every public court is priced; booking time is whole hours everywhere.
        $courts = Court::with('venue')->where('is_active', true)->get();
        $this->assertCount($courts->count(), app(CourtPriceRuleService::class)->pricedCourtIds($courts));
        $this->assertSame(0, DB::table('sport_zones')->where('slot_duration', '!=', 60)->count());
        $offHour = "extract(minute from starts_at) <> 0 or extract(second from starts_at) <> 0 or ends_at - starts_at <> interval '1 hour'";
        foreach (['reservation_slots', 'reservation_entitlements', 'booking_slots', 'court_price_slots'] as $table) {
            $this->assertGreaterThan(0, DB::table($table)->count(), $table);
            $this->assertSame(0, DB::table($table)->whereRaw($offHour)->count(), $table);
        }
        foreach (VenueBookingPolicy::all() as $policy) {
            $this->assertSame([60, 240], [data_get($policy->settings, 'booking.min_duration_minutes'), data_get($policy->settings, 'booking.max_duration_minutes')]);
            $this->assertEmpty(array_intersect_key($policy->settings, array_flip(['cancellation', 'change', 'ancillary_credit_policy'])));
            $this->assertArrayNotHasKey('seller_reimbursement_required', $policy->settings['transfer']);
        }
    }

    public function test_seeding_outside_local_and_testing_takes_receiver_values_only_from_env(): void
    {
        $this->seed(OrganizationVenueSeeder::class);
        $this->app['env'] = 'staging';
        $passwords = array_fill_keys(self::PASSWORDS, Str::password());

        foreach (array_keys(self::RECEIVER) as $missing) {
            $this->withEnv([...$passwords, ...self::RECEIVER, $missing => ''], fn () => $this->assertThrows(
                fn () => $this->seed(UserSeeder::class), RuntimeException::class, "{$missing} is not set"
            ));
            $this->assertSame(0, User::count());
        }
    }

    public function test_seeding_outside_local_and_testing_takes_passwords_only_from_env(): void
    {
        // UserSeeder attaches the accounts to these organizations and venues.
        $this->seed(OrganizationVenueSeeder::class);
        $this->app['env'] = 'staging';

        $this->withEnv([...array_fill_keys(self::PASSWORDS, ''), ...self::RECEIVER], fn () => $this->assertThrows(
            fn () => $this->seed(UserSeeder::class), RuntimeException::class, 'SEED_DEMO_PASSWORD is not set'
        ));
        $this->assertSame(0, User::count());

        $passwords = [];
        foreach (self::PASSWORDS as $key) {
            $passwords[$key] = Str::password();
        }
        $this->withEnv([...$passwords, ...self::RECEIVER], fn () => $this->seed(UserSeeder::class));

        $this->assertTrue(Hash::check($passwords['SEED_ADMIN_PASSWORD'], User::where('email', 'admin@auracourt.vn')->value('password')));
        $this->assertTrue(Hash::check($passwords['SEED_DEMO_PASSWORD'], User::where('email', 'user@example.com')->value('password')));
    }

    /**
     * Runs $callback with these env values. $_SERVER is read first by env(), so this overrides any developer .env value.
     *
     * @param  array<string, string>  $values
     */
    private function withEnv(array $values, callable $callback): void
    {
        $saved = Arr::only($_SERVER, array_keys($values));
        try {
            foreach ($values as $key => $value) {
                $_SERVER[$key] = $value;
            }
            $callback();
        } finally {
            foreach (array_keys($values) as $key) {
                unset($_SERVER[$key]);
            }
            $_SERVER += $saved;
        }
    }
}
