<?php

namespace Tests\Feature\Venue;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnifiedBookingEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomerWithWallet(int $orgId, int $balance = 500000): User
    {
        $user = User::factory()->create(['role' => 'customer']);
        $walletService = app(WalletPaymentService::class);
        $wallet = $walletService->getOrCreateWallet($user->id, $orgId);
        $walletService->credit($wallet, $balance, 'test_topup', 1, 'Fund for test');

        return $user;
    }

    public function test_direct_booking_on_draft_venue_fails_and_does_not_disclose_draft(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);

        $org = Organization::create([
            'name' => 'Draft Org',
            'slug' => 'draft-org',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        $customer = $this->createCustomerWithWallet($org->id);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Secret Draft Venue',
            'slug' => 'secret-draft-venue',
            'address' => 'Private Location',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $court = Court::create(['venue_id' => $venue->id, 'sport_type' => 'badminton', 'name' => 'Draft Court', 'slug' => 'draft-court', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => now()->addDay()->dayOfWeekIso,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer);

        // 1. Public catalog must NOT disclose draft venue
        $catalogRes = $this->getJson('/api/venues');
        $catalogRes->assertStatus(200);
        $this->assertEmpty(collect($catalogRes->json())->where('slug', 'secret-draft-venue'));

        // 2. Public venue detail by slug must return 404
        $detailRes = $this->getJson('/api/venues/secret-draft-venue');
        $detailRes->assertStatus(404);

        $this->getJson("/api/courts/{$court->slug}")->assertNotFound();

        // 3. Availability on draft court must return 404
        $availRes = $this->getJson('/api/availability?court_id='.$court->id.'&date='.now()->addDay()->toDateString());
        $availRes->assertStatus(404);

        // 4. Direct booking attempt on draft court must fail with 404
        $slotTime = now()->addDay()->setTime(8, 0, 0)->toIso8601String();
        $bookRes = $this->withHeader('Idempotency-Key', uniqid('checkout-'))->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$slotTime],
            'expected_total' => 100000,
        ]);
        $bookRes->assertStatus(404);
        $this->assertDatabaseMissing('reservations', ['court_id' => $court->id]);
    }

    public function test_cached_availability_after_suspension_refuses_new_booking(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);

        $org = Organization::create([
            'name' => 'Active Org',
            'slug' => 'active-org',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        $customer = $this->createCustomerWithWallet($org->id);
        $plan = PlatformPlan::create(['code' => 'PL1', 'name' => 'Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Public Venue',
            'slug' => 'public-venue',
            'address' => '123 Main Street',
            'status' => 'published',
            'is_active' => true,
            'approved_public_snapshot' => ['venue' => ['name' => 'Public Venue']],
        ]);

        $court = Court::create(['venue_id' => $venue->id, 'sport_type' => 'badminton', 'name' => 'Court 1', 'slug' => 'court-1', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => now()->addDay()->dayOfWeekIso,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer);

        // Warm public list caches before the partner changes state.
        $this->getJson('/api/venues')->assertOk()->assertJsonFragment(['slug' => 'public-venue']);
        $this->getJson('/api/courts')->assertOk()->assertJsonFragment(['id' => $court->id]);

        // Customer loaded availability earlier:
        $availRes = $this->getJson('/api/availability?court_id='.$court->id.'&date='.now()->addDay()->toDateString());
        $availRes->assertStatus(200);

        // Partner organization gets suspended!
        $org->update(['partner_status' => 'suspended']);

        $this->getJson('/api/venues')->assertOk()->assertJsonMissing(['slug' => 'public-venue']);
        $this->getJson('/api/courts')->assertOk()->assertJsonMissing(['id' => $court->id]);
        $this->getJson('/api/venues/public-venue')->assertNotFound();
        $this->getJson("/api/courts/{$court->slug}")->assertNotFound();

        // Customer attempts to submit booking with the previously-loaded slot
        $slotTime = now()->addDay()->setTime(8, 0, 0)->toIso8601String();
        $bookRes = $this->withHeader('Idempotency-Key', uniqid('checkout-'))->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$slotTime],
            'expected_total' => 100000,
        ]);

        // Must be rejected!
        $this->assertNotEquals(201, $bookRes->status());
        $this->assertDatabaseMissing('reservations', ['court_id' => $court->id]);
    }

    public function test_expired_subscription_is_rechecked_on_cached_public_lists_and_quote(): void
    {
        Config::set('partner.enforce_publication_check', true);
        Config::set('partner.enforce_subscription_check', true);
        $this->travelTo(Carbon::create(2026, 9, 25, 10, 0, 0, 'Asia/Ho_Chi_Minh'));

        $owner = User::factory()->create(['role' => 'customer']);
        $customer = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Expiring Public Org',
            'slug' => 'expiring-public-org',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $customer->id, 'role' => 'customer', 'status' => 'active']);
        $plan = PlatformPlan::create(['code' => 'EXP-CACHE', 'name' => 'Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        $subscription = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Expiring Public Venue',
            'slug' => 'expiring-public-venue',
            'address' => '321 Cache Street',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'sport_type' => 'badminton', 'name' => 'Court', 'slug' => 'expiring-court', 'is_active' => true]);
        $slotDate = now()->addDays(4);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $slotDate->dayOfWeekIso,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'price' => 100000,
            'is_active' => true,
        ]);
        Sanctum::actingAs($customer);

        $this->getJson('/api/venues')->assertOk()->assertJsonFragment(['slug' => $venue->slug]);
        $this->getJson('/api/courts')->assertOk()->assertJsonFragment(['id' => $court->id]);
        $this->getJson("/api/venues/{$venue->slug}")->assertOk();

        $this->travel(2)->days();
        $this->getJson('/api/venues')->assertOk()->assertJsonMissing(['slug' => $venue->slug]);
        $this->getJson('/api/courts')->assertOk()->assertJsonMissing(['id' => $court->id]);
        $this->getJson("/api/venues/{$venue->slug}")->assertNotFound();
        $this->getJson("/api/courts/{$court->slug}")->assertNotFound();
        $this->assertSame('expired', $subscription->fresh()->status);

        $slot = now()->addDays(4)->setTime(8, 0)->toIso8601String();
        $this->postJson('/api/price-quotes', [
            'court_id' => $court->id,
            'slots' => [$slot],
        ], ['Idempotency-Key' => 'expired-public-quote'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subscription']);
    }
}
