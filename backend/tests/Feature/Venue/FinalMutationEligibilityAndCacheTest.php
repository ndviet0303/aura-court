<?php

namespace Tests\Feature\Venue;

use App\Domain\Partner\Actions\RestorePartner;
use App\Domain\Partner\Actions\SuspendPartner;
use App\Domain\Venue\Actions\TransitionVenueLifecycle;
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
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinalMutationEligibilityAndCacheTest extends TestCase
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

    public function test_cache_version_incremented_on_partner_and_venue_state_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'customer']);

        $org = Organization::create([
            'name' => 'Cache Test Org',
            'slug' => 'cache-test-org',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create(['code' => 'P1', 'name' => 'Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Cache Venue',
            'slug' => 'cache-venue',
            'address' => 'Cache Address',
            'status' => 'published',
            'is_active' => true,
        ]);

        Cache::forever('venues_version', 1);
        $initialVersion = (int) Cache::get('venues_version');
        // 1. Suspend partner increments venues_version
        $suspendAction = app(SuspendPartner::class);
        $suspendAction->execute($org->id, $admin->id, 'Vi phạm quy chế');
        $afterSuspendVersion = (int) Cache::get('venues_version');
        $this->assertGreaterThan($initialVersion, $afterSuspendVersion);

        // 2. Restore partner increments venues_version
        $restoreAction = app(RestorePartner::class);
        $restoreAction->execute($org->id, $admin->id);
        $afterRestoreVersion = (int) Cache::get('venues_version');
        $this->assertGreaterThan($afterSuspendVersion, $afterRestoreVersion);

        // 3. Venue lifecycle change increments venues_version
        $lifecycleAction = app(TransitionVenueLifecycle::class);
        $lifecycleAction->execute($venue->id, $admin->id, 'temporarily_closed', 'Bảo trì', isPlatformAdmin: true);
        $afterVenueChangeVersion = (int) Cache::get('venues_version');
        $this->assertGreaterThan($afterRestoreVersion, $afterVenueChangeVersion);
    }

    public function test_final_mutation_time_check_rejects_closed_venue(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);

        $org = Organization::create([
            'name' => 'Fast Org',
            'slug' => 'fast-org',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $customer = $this->createCustomerWithWallet($org->id);

        $plan = PlatformPlan::create(['code' => 'PF1', 'name' => 'Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Race Venue',
            'slug' => 'race-venue',
            'address' => 'Fast Address',
            'status' => 'published',
            'is_active' => true,
            'approved_public_snapshot' => ['venue' => ['name' => 'Race Venue']],
        ]);

        $court = Court::create(['venue_id' => $venue->id, 'sport_type' => 'badminton', 'name' => 'Court R', 'slug' => 'court-r', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => now()->addDay()->dayOfWeekIso,
            'starts_at' => '08:00',
            'ends_at' => '09:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        // Venue is closed right before transaction
        $venue->update(['status' => 'temporarily_closed', 'is_active' => false]);

        Sanctum::actingAs($customer);
        $slotTime = now()->addDay()->setTime(8, 0, 0)->toIso8601String();
        $bookRes = $this->withHeader('Idempotency-Key', uniqid('checkout-'))->postJson('/api/reservations/checkout', [
            'court_id' => $court->id,
            'slots' => [$slotTime],
            'expected_total' => 100000,
        ]);

        $this->assertNotEquals(201, $bookRes->status());
        $this->assertDatabaseMissing('reservations', ['court_id' => $court->id]);
    }
}
