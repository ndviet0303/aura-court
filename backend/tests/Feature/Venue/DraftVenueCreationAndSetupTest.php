<?php

namespace Tests\Feature\Venue;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DraftVenueCreationAndSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_draft_venue_without_auto_publication(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Draft Venue Club',
            'slug' => 'draft-venue-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => 'organization_owner',
            'status' => 'active',
        ]);

        $plan = PlatformPlan::create([
            'code' => 'ACTIVE_PLAN',
            'name' => 'Active Plan',
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'max_venues' => 2,
            'max_courts' => 10,
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        Sanctum::actingAs($owner);

        // Create venue
        $res = $this->postJson("/api/partner/organizations/{$org->id}/venues", [
            'name' => 'Sân Cầu Lông Ba Đình',
            'address' => '10 Chu Van An, Ba Dinh, Ha Noi',
            'city' => 'Hà Nội',
            'latitude' => 21.0333,
            'longitude' => 105.84,
            'amenities' => ['wifi', 'parking', 'shower'],
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.latitude', 21.0333)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.is_active', false); // MUST be non-public draft!

        $venueId = $res->json('data.id');
        $this->assertDatabaseHas('venues', [
            'id' => $venueId,
            'organization_id' => $org->id,
            'status' => 'draft',
            'is_active' => false,
        ]);

        // Update draft venue
        $updateRes = $this->putJson("/api/partner/organizations/{$org->id}/venues/{$venueId}", [
            'address' => '12 Chu Van An, Ba Dinh, Ha Noi',
            'latitude' => 21.034,
            'longitude' => 105.841,
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.address', '12 Chu Van An, Ba Dinh, Ha Noi')
            ->assertJsonPath('data.longitude', 105.841)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.is_active', false); // Still non-public!
    }

    public function test_non_owner_cannot_create_venue(): void
    {
        $nonOwner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);

        Sanctum::actingAs($nonOwner);

        $res = $this->postJson("/api/partner/organizations/{$org->id}/venues", [
            'name' => 'Illegal Venue',
            'address' => 'Somewhere',
        ]);

        $res->assertStatus(403);
    }
}
