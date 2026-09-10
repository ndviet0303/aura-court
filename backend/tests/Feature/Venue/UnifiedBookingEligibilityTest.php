<?php

namespace Tests\Feature\Venue;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\SportZone;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnifiedBookingEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomerWithWallet(int $orgId, int $balance = 500000): User
    {
        $user = User::factory()->create(['role' => 'customer']);
        $walletService = app(\App\Services\Payment\WalletPaymentService::class);
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

        $sportZone = SportZone::create(['venue_id' => $venue->id, 'name' => 'Zone 1', 'sport_type' => 'badminton', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'sport_zone_id' => $sportZone->id, 'name' => 'Draft Court', 'slug' => 'draft-court', 'is_active' => true]);
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
        $catalogRes = $this->getJson('/api/v1/venues');
        $catalogRes->assertStatus(200);
        $this->assertEmpty(collect($catalogRes->json())->where('slug', 'secret-draft-venue'));

        // 2. Public venue detail by slug must return 404
        $detailRes = $this->getJson('/api/v1/venues/secret-draft-venue');
        $detailRes->assertStatus(404);

        // 3. Availability on draft court must return 404
        $availRes = $this->getJson('/api/v1/availability?court_id=' . $court->id . '&date=' . now()->addDay()->toDateString());
        $availRes->assertStatus(404);

        // 4. Direct booking attempt on draft court must fail with 404
        $slotTime = now()->addDay()->setTime(8, 0, 0)->toIso8601String();
        $bookRes = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$slotTime],
        ]);
        $bookRes->assertStatus(404);
        $this->assertDatabaseMissing('bookings', ['court_id' => $court->id]);
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

        $sportZone = SportZone::create(['venue_id' => $venue->id, 'name' => 'Zone 1', 'sport_type' => 'badminton', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'sport_zone_id' => $sportZone->id, 'name' => 'Court 1', 'slug' => 'court-1', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => now()->addDay()->dayOfWeekIso,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer);

        // Customer loaded availability earlier:
        $availRes = $this->getJson('/api/v1/availability?court_id=' . $court->id . '&date=' . now()->addDay()->toDateString());
        $availRes->assertStatus(200);

        // Partner organization gets suspended!
        $org->update(['partner_status' => 'suspended']);

        // Customer attempts to submit booking with the previously-loaded slot
        $slotTime = now()->addDay()->setTime(8, 0, 0)->toIso8601String();
        $bookRes = $this->postJson('/api/v1/bookings', [
            'court_id' => $court->id,
            'slots' => [$slotTime],
        ]);

        // Must be rejected!
        $this->assertNotEquals(201, $bookRes->status());
        $this->assertDatabaseMissing('bookings', ['court_id' => $court->id]);
    }
}
