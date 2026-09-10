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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VenueReadinessChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_venue_returns_actionable_missing_items(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Incomplete Club',
            'slug' => 'incomplete-club',
            'status' => 'active',
            'partner_status' => 'pending', // Not approved yet!
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Sân Cầu Lông',
            'slug' => 'san-cau-long',
            'address' => '123 Đường Số 1',
            'status' => 'draft',
            'is_active' => false,
        ]);

        Sanctum::actingAs($owner);

        // Check readiness: No subscription, partner pending, no courts
        $res = $this->getJson("/api/v1/partner/organizations/{$org->id}/venues/{$venue->id}/readiness");

        $res->assertStatus(200)
            ->assertJsonPath('data.is_ready', false)
            ->assertJsonStructure(['data' => ['missing_items', 'checklist']]);

        $missingCodes = collect($res->json('data.missing_items'))->pluck('code')->all();

        $this->assertContains('ORG_NOT_APPROVED_PARTNER', $missingCodes);
        $this->assertContains('SUBSCRIPTION_REQUIRED', $missingCodes);
        $this->assertContains('NO_ACTIVE_COURTS', $missingCodes);
    }

    public function test_missing_court_prices_blocks_readiness(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Price Check Club',
            'slug' => 'price-check-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create(['code' => 'P', 'name' => 'Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Sân Hà Nội',
            'slug' => 'san-ha-noi',
            'address' => '99 Tran Duy Hung',
            'status' => 'draft',
            'is_active' => false,
        ]);

        // Create active court without price slots!
        Court::create([
            'venue_id' => $venue->id,
            'name' => 'Sân 1',
            'slug' => 'san-1',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        $res = $this->getJson("/api/v1/partner/organizations/{$org->id}/venues/{$venue->id}/readiness");
        $res->assertStatus(200)
            ->assertJsonPath('data.is_ready', false);

        $missingCodes = collect($res->json('data.missing_items'))->pluck('code')->all();
        $this->assertContains('COURT_PRICES_MISSING', $missingCodes);
    }

    public function test_complete_venue_passes_readiness_and_optional_staff_does_not_block(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Ready Club',
            'slug' => 'ready-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create(['code' => 'P_READY', 'name' => 'Plan', 'price' => 200000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Sân Cầu Lông Đạt Chuẩn',
            'slug' => 'san-dat-chuan',
            'address' => '100 Hoang Quoc Viet, Cau Giay, Ha Noi',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Sân Thi Đấu 1',
            'slug' => 'san-thi-dau-1',
            'is_active' => true,
        ]);

        // Add active price slot
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 120000,
            'is_active' => true,
        ]);

        // Note: Organization has 0 staff and 0 POS products.
        Sanctum::actingAs($owner);

        $res = $this->getJson("/api/v1/partner/organizations/{$org->id}/venues/{$venue->id}/readiness");

        $res->assertStatus(200)
            ->assertJsonPath('data.is_ready', true)
            ->assertJsonCount(0, 'data.missing_items')
            ->assertJsonPath('data.checklist.partner_approved', true)
            ->assertJsonPath('data.checklist.subscription_entitled', true)
            ->assertJsonPath('data.checklist.active_courts', true)
            ->assertJsonPath('data.checklist.court_pricing', true);
    }
}
