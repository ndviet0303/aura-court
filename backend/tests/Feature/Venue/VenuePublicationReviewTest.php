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

class VenuePublicationReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_incomplete_venue_fails_and_leaves_in_draft(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Incomplete Org', 'slug' => 'inc-org', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Incomplete Venue',
            'slug' => 'inc-venue',
            'address' => 'Short', // Invalid address
            'status' => 'draft',
            'is_active' => false,
        ]);

        Sanctum::actingAs($owner);

        $res = $this->postJson("/api/v1/partner/organizations/{$org->id}/venues/{$venue->id}/submit-publication");

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['readiness']);

        $this->assertEquals('draft', $venue->fresh()->status);
        $this->assertFalse($venue->fresh()->is_active);
    }

    public function test_submit_and_approve_publication_flow(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $org = Organization::create(['name' => 'Ready Org', 'slug' => 'ready-org', 'status' => 'active', 'partner_status' => 'approved']);
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
            'name' => 'San Cau Long Cau Giay',
            'slug' => 'san-cau-long-cau-giay',
            'address' => '123 Tran Thai Tong, Cau Giay, Ha Noi',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'San 1', 'slug' => 'san-1', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        // 1. Owner submits venue for publication
        Sanctum::actingAs($owner);
        $submitRes = $this->postJson("/api/v1/partner/organizations/{$org->id}/venues/{$venue->id}/submit-publication");

        $submitRes->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.revision_number', 1);

        $revisionId = $submitRes->json('data.id');
        $this->assertEquals('pending_publication', $venue->fresh()->status);
        $this->assertFalse($venue->fresh()->is_active);

        // 2. Admin inspects publication queue
        Sanctum::actingAs($admin);
        $queueRes = $this->getJson('/api/v1/admin/venues/publication-queue');
        $queueRes->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        // 3. Admin approves publication
        $reviewRes = $this->postJson("/api/v1/admin/venues/revisions/{$revisionId}/review", [
            'decision' => 'approve',
            'reason' => 'Đạt tiêu chuẩn xuất bản cơ sở thể thao.',
        ]);

        $reviewRes->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $freshVenue = $venue->fresh();
        $this->assertEquals('published', $freshVenue->status);
        $this->assertTrue($freshVenue->is_active);
        $this->assertNotNull($freshVenue->published_at);
        $this->assertNotNull($freshVenue->approved_public_snapshot);
        $this->assertEquals('San Cau Long Cau Giay', $freshVenue->approved_public_snapshot['venue']['name']);
    }

    public function test_reviewer_requests_corrections_and_rejects(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $org = Organization::create(['name' => 'Org 3', 'slug' => 'org-3', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create(['code' => 'P3', 'name' => 'Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Sân Đang Chỉnh Sửa',
            'slug' => 'san-dang-chinh-sua',
            'address' => '456 Le Duan, Da Nang',
            'status' => 'draft',
            'is_active' => false,
        ]);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'San A', 'slug' => 'san-a', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $submitRes = $this->postJson("/api/v1/partner/organizations/{$org->id}/venues/{$venue->id}/submit-publication");
        $revisionId = $submitRes->json('data.id');

        // Reviewer requests corrections
        Sanctum::actingAs($admin);
        $revRes = $this->postJson("/api/v1/admin/venues/revisions/{$revisionId}/review", [
            'decision' => 'revision_required',
            'reason' => 'Ảnh đại diện sân chụp mờ, vui lòng tải ảnh rõ nét hơn.',
            'feedback_fields' => ['images' => 'Vui lòng bổ sung ảnh sân rõ nét.'],
        ]);

        $revRes->assertStatus(200)
            ->assertJsonPath('data.status', 'revision_required');

        $this->assertEquals('revision_required', $venue->fresh()->status);
        $this->assertFalse($venue->fresh()->is_active);
    }
}
