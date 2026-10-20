<?php

namespace Tests\Feature\Venue;

use App\Models\Booking;
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

class VenueLifecycleTransitionsTest extends TestCase
{
    use RefreshDatabase;

    private function createPublishedVenue(): array
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $org = Organization::create([
            'name' => 'Lifecycle Org',
            'slug' => 'lifecycle-org',
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
            'code' => 'PL_LC',
            'name' => 'Lifecycle Plan',
            'price' => 100000,
            'billing_cycle' => 'monthly',
            'is_active' => true,
        ]);
        $sub = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'San Lifecycle',
            'slug' => 'san-lifecycle',
            'address' => '100 Nguyen Van Linh, Da Nang',
            'status' => 'published',
            'is_active' => true,
            'approved_public_snapshot' => ['venue' => ['name' => 'San Lifecycle']],
        ]);

        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Court A',
            'slug' => 'court-a',
            'is_active' => true,
        ]);

        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'court_id' => $court->id,
            'user_id' => $customer->id,
            'code' => 'BK-LC-001',
            'status' => 'confirmed',
            'starts_at' => now()->addDays(2)->setTime(8, 0),
            'ends_at' => now()->addDays(2)->setTime(9, 0),
            'total_amount' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$owner, $admin, $org, $sub, $venue, $court, $booking];
    }

    public function test_owner_transitions_venue_and_preserves_existing_bookings(): void
    {
        [$owner, $admin, $org, $sub, $venue, $court, $booking] = $this->createPublishedVenue();

        Sanctum::actingAs($owner);

        // 1. Owner closes venue temporarily
        $res1 = $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/lifecycle", [
            'status' => 'temporarily_closed',
            'reason' => 'Sửa chữa điện lưới.',
        ]);
        $res1->assertStatus(200);
        $this->assertEquals('temporarily_closed', $venue->fresh()->status);
        $this->assertFalse($venue->fresh()->is_active);

        // Check that existing booking is still intact!
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);

        // 2. Owner closes venue permanently
        $res2 = $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/lifecycle", [
            'status' => 'closed',
            'reason' => 'Chấm dứt hợp đồng mặt bằng.',
        ]);
        $res2->assertStatus(200);
        $this->assertEquals('closed', $venue->fresh()->status);

        // Existing booking still preserved!
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_platform_suspension_requires_admin(): void
    {
        [$owner, $admin, $org, $sub, $venue] = $this->createPublishedVenue();

        // Non-admin attempts to suspend
        Sanctum::actingAs($owner);
        $res1 = $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/lifecycle", [
            'status' => 'suspended',
            'reason' => 'Hack attempt',
        ]);
        $res1->assertStatus(422); // Validation error: 'suspended' not in allowed list for partner endpoint

        // Admin suspends venue via admin endpoint
        Sanctum::actingAs($admin);
        $res2 = $this->postJson("/api/admin/venues/{$venue->id}/lifecycle", [
            'status' => 'suspended',
            'reason' => 'Vi phạm quy chế an toàn sân bãi.',
        ]);
        $res2->assertStatus(200);
        $this->assertEquals('suspended', $venue->fresh()->status);
        $this->assertFalse($venue->fresh()->is_active);

        // Owner cannot lift platform suspension
        Sanctum::actingAs($owner);
        $res3 = $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/lifecycle", [
            'status' => 'published',
        ]);
        $res3->assertStatus(403);
    }

    public function test_owner_cannot_leave_a_platform_suspension_through_any_transition(): void
    {
        [$owner, $admin, $org, , $venue] = $this->createPublishedVenue();
        $venue->update(['status' => 'suspended', 'is_active' => false]);

        Sanctum::actingAs($owner);
        foreach (['temporarily_closed', 'closed', 'archived'] as $target) {
            $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/lifecycle", ['status' => $target])
                ->assertForbidden();
            $this->assertSame('suspended', $venue->fresh()->status);
        }

        // The platform admin still lifts it the existing way; publishing again rechecks readiness
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/venues/{$venue->id}/lifecycle", ['status' => 'published'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['readiness']);
        $this->postJson("/api/admin/venues/{$venue->id}/lifecycle", ['status' => 'temporarily_closed'])->assertOk();
        $this->assertSame('temporarily_closed', $venue->fresh()->status);
    }

    public function test_suspended_closed_or_archived_venue_cannot_be_submitted_for_publication(): void
    {
        [$owner, , $org, , $venue] = $this->createPublishedVenue();

        Sanctum::actingAs($owner);
        foreach (['suspended', 'closed', 'archived'] as $status) {
            $venue->update(['status' => $status, 'is_active' => false]);
            $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/submit-publication")
                ->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
            $this->assertSame($status, $venue->fresh()->status);
        }

        $this->assertDatabaseCount('venue_publication_revisions', 0);
    }

    public function test_closed_or_archived_venue_cannot_be_reopened_by_anyone(): void
    {
        [$owner, $admin, $org, , $venue] = $this->createPublishedVenue();
        $ownerUrl = "/api/partner/organizations/{$org->id}/venues/{$venue->id}/lifecycle";
        $adminUrl = "/api/admin/venues/{$venue->id}/lifecycle";
        $attempts = [
            [$owner, $ownerUrl, 'published'],
            [$owner, $ownerUrl, 'temporarily_closed'],
            [$admin, $adminUrl, 'published'],
            [$admin, $adminUrl, 'suspended'],
        ];

        foreach (['closed', 'archived'] as $status) {
            $venue->update(['status' => $status, 'is_active' => false]);
            foreach ($attempts as [$actor, $url, $target]) {
                Sanctum::actingAs($actor);
                $this->postJson($url, ['status' => $target])
                    ->assertStatus(422)
                    ->assertJsonValidationErrors(['status']);
                $this->assertSame($status, $venue->fresh()->status);
            }
        }

        $venue->update(['status' => 'closed']);
        Sanctum::actingAs($owner);
        $this->postJson($ownerUrl, ['status' => 'archived'])->assertOk();
        $this->assertSame('archived', $venue->fresh()->status);
    }

    public function test_unreviewed_venue_cannot_reach_published_through_temporarily_closed(): void
    {
        [$owner, , $org, , $venue] = $this->createPublishedVenue();
        $url = "/api/partner/organizations/{$org->id}/venues/{$venue->id}/lifecycle";

        Sanctum::actingAs($owner);
        foreach (['draft', 'pending_publication', 'revision_required'] as $status) {
            $venue->update(['status' => $status, 'is_active' => false]);
            $this->postJson($url, ['status' => 'temporarily_closed'])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
            $this->assertSame($status, $venue->fresh()->status);
        }
    }

    public function test_restoration_to_published_fails_if_subscription_expired(): void
    {
        [$owner, $admin, $org, $sub, $venue] = $this->createPublishedVenue();

        // Temporarily close
        Sanctum::actingAs($owner);
        $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/lifecycle", [
            'status' => 'temporarily_closed',
            'reason' => 'Bảo trì',
        ]);

        // Subscription expires
        $sub->update([
            'status' => 'expired',
            'ends_at' => now()->subDay(),
        ]);

        // Attempt restoration
        $res = $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/lifecycle", [
            'status' => 'published',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['readiness']);

        $this->assertEquals('temporarily_closed', $venue->fresh()->status);
        $this->assertFalse($venue->fresh()->is_active);
    }
}
