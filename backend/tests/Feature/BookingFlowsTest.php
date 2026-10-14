<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\PriceRule;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::first();
        if ($org) {
            $org->update(['partner_status' => 'approved', 'status' => 'active']);
            $plan = PlatformPlan::firstOrCreate(
                ['code' => 'PLATFORM_GROWTH'],
                ['name' => 'Growth', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]
            );
            PlatformSubscription::firstOrCreate(
                ['organization_id' => $org->id],
                ['plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()]
            );
        }

        $venue = Venue::first();
        if ($venue) {
            $venue->update(['status' => 'published', 'is_active' => true]);
        }
    }

    private function fundUserWallet(User $user, int $amount = 1000000): void
    {
        $venue = Venue::firstOrFail();
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $venue->organization_id);
        $walletService->credit($wallet, $amount, 'test_topup', 1, 'Fund for test');
    }

    public function test_customer_cannot_access_admin_endpoints(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->getJson('/api/admin/reports')->assertForbidden();
    }

    public function test_admin_can_create_court_price_and_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $venue = Venue::firstOrFail();

        $court = $this->actingAs($admin)->postJson('/api/admin/courts', ['name' => 'Sân Test', 'venue_id' => $venue->id])->assertCreated()->json();
        $courtId = $court['id'];

        $this->actingAs($admin)->postJson("/api/admin/courts/{$courtId}/prices", [
            'weekday' => 1, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 150000,
        ])->assertCreated();
        $this->actingAs($admin)->postJson("/api/admin/courts/{$courtId}/blocks", [
            'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'ends_at' => now()->addDay()->setTime(11, 0)->toIso8601String(),
            'reason' => 'Bảo trì',
        ])->assertCreated();

        $this->assertDatabaseHas('court_price_slots', ['court_id' => $courtId, 'price' => 150000]);
        $this->assertDatabaseHas('court_blocks', ['court_id' => $courtId, 'reason' => 'Bảo trì']);
    }

    public function test_sepay_webhook_deposits_into_wallet(): void
    {
        config(['services.sepay.hmac_secret' => 'test-secret']);
        $user = User::factory()->create();
        $venue = Venue::firstOrFail();
        app(WalletPaymentService::class)->getOrCreateWallet($user->id, $venue->organization_id);

        $payload = ['id' => 'tx-deposit-1', 'code' => "ORD NAP {$user->id}", 'transferAmount' => 500000, 'transferType' => 'in'];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $raw, 'test-secret');

        $this->call('POST', '/api/v1/webhooks/sepay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SEPAY_SIGNATURE' => $signature], $raw)->assertOk();

        $this->assertDatabaseHas('payments', ['external_transaction_id' => 'tx-deposit-1', 'status' => 'paid']);
        $this->assertDatabaseHas('wallet_accounts', ['user_id' => $user->id, 'balance' => 500000]);
    }

    public function test_two_customers_cannot_hold_the_same_slot(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $venue = Venue::firstOrFail();
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Conflict', 'is_active' => true]);
        $date = Carbon::now()->addDays(3)->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        $this->actingAs($first)->withHeader('Idempotency-Key', uniqid('hold-'))->postJson('/api/reservations', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]])->assertCreated();
        $this->actingAs($second)->withHeader('Idempotency-Key', uniqid('hold-'))->postJson('/api/reservations', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]])->assertStatus(409);
    }

    public function test_booking_rejects_past_and_unaligned_slots(): void
    {
        $user = User::factory()->create();
        $this->fundUserWallet($user, 500000);
        $venue = Venue::firstOrFail();
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Validation', 'is_active' => true]);
        PriceRule::create(['organization_id' => $venue->organization_id, 'court_id' => $court->id, 'type' => 'base', 'price' => 100000, 'is_active' => true]);
        $past = now()->subDay()->setTime(10, 0);

        $this->actingAs($user)->withHeader('Idempotency-Key', uniqid('checkout-'))->postJson('/api/reservations/checkout', ['court_id' => $court->id, 'slots' => [$past->toIso8601String()]])->assertUnprocessable();
        $future = now()->addDay()->setTime(10, 15);
        $this->actingAs($user)->withHeader('Idempotency-Key', uniqid('checkout-'))->postJson('/api/reservations/checkout', ['court_id' => $court->id, 'slots' => [$future->toIso8601String()]])->assertUnprocessable();
    }

    public function test_admin_can_list_and_delete_a_block(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $venue = Venue::firstOrFail();
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Block CRUD', 'is_active' => true]);
        $block = $court->blocks()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'reason' => 'Bảo trì']);

        $this->actingAs($admin)->getJson("/api/admin/courts/{$court->id}/blocks")->assertOk()->assertJsonPath('data.0.id', $block->id);
        $this->actingAs($admin)->deleteJson("/api/admin/courts/{$court->id}/blocks/{$block->id}")->assertOk();
        $this->assertDatabaseMissing('court_blocks', ['id' => $block->id]);
    }

    public function test_customer_can_reload_payment_from_booking_code(): void
    {
        $user = User::factory()->create();
        $this->fundUserWallet($user, 500000);
        $venue = Venue::firstOrFail();
        OrganizationMember::create(['organization_id' => $venue->organization_id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Payment Reload', 'is_active' => true]);
        $date = now()->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);
        $code = $this->actingAs($user)->withHeader('Idempotency-Key', uniqid('checkout-'))
            ->postJson('/api/reservations/checkout', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]])
            ->assertCreated()
            ->json('reservation.code');

        $this->actingAs($user)->getJson("/api/reservations/{$code}/payment")
            ->assertOk()
            ->assertJsonPath('reservation.code', $code)
            ->assertJsonPath('reservation.status', 'confirmed');
    }

    public function test_recurring_booking_creates_all_weeks_atomically(): void
    {
        $user = User::factory()->create();
        $this->fundUserWallet($user, 1000000);
        $venue = Venue::firstOrFail();
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Recurring', 'is_active' => true]);
        $date = now()->addDays(3)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        $response = $this->actingAs($user)->withHeader('Idempotency-Key', uniqid('series-'))->postJson('/api/recurring-series', [
            'court_id' => $court->id,
            'frequency' => 'weekly',
            'starts_at' => $date->toIso8601String(),
            'occurrences' => 2,
        ]);
        $response->assertCreated()->assertJsonCount(2, 'series.occurrences');
        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_unverified_customer_cannot_create_booking(): void
    {
        $user = User::factory()->unverified()->create();
        $this->fundUserWallet($user, 500000);
        $venue = Venue::firstOrFail();
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Sân Verify', 'is_active' => true]);
        $date = now()->addDays(2)->setTime(10, 0);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $date->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 100000, 'is_active' => true]);

        $this->actingAs($user)->withHeader('Idempotency-Key', uniqid('checkout-'))->postJson('/api/reservations/checkout', ['court_id' => $court->id, 'slots' => [$date->toIso8601String()]])->assertForbidden();
        $this->assertDatabaseCount('reservations', 0);
    }
}
