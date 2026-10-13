<?php

namespace Tests\Feature\Venue;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Models\VenueOperatingSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VenuePublicSnapshotAndOperationalClosureTest extends TestCase
{
    use RefreshDatabase;

    private function addPolicy(Venue $venue, int $ownerId): void
    {
        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $ownerId,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 15, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'cancellation' => ['deadline_hours' => 24, 'fee_type' => 'none', 'fee_value' => 0, 'refund_due_hours' => 48],
                'change' => ['deadline_hours' => 12, 'fee_type' => 'none', 'fee_value' => 0],
                'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 10, 'no_show_after_minutes' => 30],
                'recurring' => ['payment_policy' => 'per_period', 'conflict_policy' => 'all_or_nothing', 'overdue_release_hours' => 24],
                'transfer' => ['cutoff_hours' => 2, 'max_consideration_percent' => 100, 'free_transfer_allowed' => false, 'seller_reimbursement_required' => true],
                'ancillary_credit_policy' => 'refund',
            ],
        ]);
    }

    private function addOperatingSchedule(Venue $venue, int $ownerId): void
    {
        $hours = [];
        for ($day = 1; $day <= 7; $day++) {
            $hours[(string) $day] = ['closed' => false, 'intervals' => [['start' => '06:00', 'end' => '22:00']]];
        }
        VenueOperatingSchedule::create([
            'venue_id' => $venue->id,
            'scope_key' => 'venue',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'version' => 1,
            'weekly_hours' => $hours,
            'created_by' => $ownerId,
        ]);
    }

    public function test_public_snapshot_isolation_during_material_changes(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $org = Organization::create(['name' => 'Club Alpha', 'slug' => 'club-alpha', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

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
            'name' => 'San Alpha',
            'slug' => 'san-alpha',
            'address' => '789 Hoang Hoa Tham, Ba Dinh, Ha Noi',
            'status' => 'draft',
            'is_active' => false,
        ]);
        $this->addPolicy($venue, $owner->id);
        $this->addOperatingSchedule($venue, $owner->id);
        $this->selectVerifiedReceiver($venue, $owner);

        $court1 = Court::create(['venue_id' => $venue->id, 'name' => 'Court 1', 'slug' => 'court-1', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court1->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        // Submit & approve Revision 1
        Sanctum::actingAs($owner);
        $subRes1 = $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/submit-publication");
        $rev1Id = $subRes1->json('data.id');

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/venues/revisions/{$rev1Id}/review", ['decision' => 'approve']);

        $snapshot1 = $venue->fresh()->approved_public_snapshot;
        $this->assertCount(1, $snapshot1['courts']);
        $this->assertEquals('Court 1', $snapshot1['courts'][0]['name']);

        // Owner adds Court 2 (material change)
        $court2 = Court::create(['venue_id' => $venue->id, 'name' => 'Court 2', 'slug' => 'court-2', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court2->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 120000,
            'is_active' => true,
        ]);

        // Public snapshot MUST STILL only contain Court 1!
        $this->assertCount(1, $venue->fresh()->approved_public_snapshot['courts']);

        // Owner submits Revision 2
        Sanctum::actingAs($owner);
        $subRes2 = $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/submit-publication");
        $rev2Id = $subRes2->json('data.id');
        $this->assertEquals(2, $subRes2->json('data.revision_number'));

        // Before approval of Rev 2, public snapshot remains Rev 1
        $this->assertCount(1, $venue->fresh()->approved_public_snapshot['courts']);

        // Admin approves Revision 2
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/venues/revisions/{$rev2Id}/review", ['decision' => 'approve']);

        // Now public snapshot contains both courts!
        $snapshot2 = $venue->fresh()->approved_public_snapshot;
        $this->assertCount(2, $snapshot2['courts']);
    }

    public function test_expired_subscription_during_review_blocks_approval(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $org = Organization::create(['name' => 'Club Exp', 'slug' => 'club-exp', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create(['code' => 'PEXP', 'name' => 'Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        $sub = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'San Exp',
            'slug' => 'san-exp',
            'address' => '12 Nguyen Trai, Thanh Xuan, Ha Noi',
            'status' => 'draft',
            'is_active' => false,
        ]);
        $this->addPolicy($venue, $owner->id);
        $this->addOperatingSchedule($venue, $owner->id);
        $this->selectVerifiedReceiver($venue, $owner);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'San 1', 'slug' => 'san-1', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $subRes = $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/submit-publication");
        $revId = $subRes->json('data.id');

        // Subscription expires while in review queue
        $sub->update([
            'status' => 'expired',
            'ends_at' => now()->subMinutes(10),
        ]);

        // Admin tries to approve
        Sanctum::actingAs($admin);
        $res = $this->postJson("/api/admin/venues/revisions/{$revId}/review", ['decision' => 'approve']);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['readiness']);

        // Venue must NOT be published
        $this->assertFalse($venue->fresh()->is_active);
        $this->assertNotEquals('published', $venue->fresh()->status);
    }

    public function test_stale_review_decision_returns_409_conflict(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $org = Organization::create(['name' => 'Club Stale', 'slug' => 'club-stale', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create(['code' => 'PST', 'name' => 'Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'San Stale',
            'slug' => 'san-stale',
            'address' => '50 Pho Hue, Hai Ba Trung, Ha Noi',
            'status' => 'draft',
            'is_active' => false,
        ]);
        $this->addPolicy($venue, $owner->id);
        $this->addOperatingSchedule($venue, $owner->id);
        $this->selectVerifiedReceiver($venue, $owner);

        $court = Court::create(['venue_id' => $venue->id, 'name' => 'San 1', 'slug' => 'san-1', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 100000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $subRes = $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/submit-publication");
        $revId = $subRes->json('data.id');

        Sanctum::actingAs($admin);
        $res1 = $this->postJson("/api/admin/venues/revisions/{$revId}/review", ['decision' => 'approve']);
        $res1->assertStatus(200);

        // Second review on already-settled revision returns 409
        $res2 = $this->postJson("/api/admin/venues/revisions/{$revId}/review", ['decision' => 'approve']);
        $res2->assertStatus(409);
    }

    public function test_immediate_operational_closure_and_reopen(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);

        $org = Organization::create(['name' => 'Club Ops', 'slug' => 'club-ops', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'San Ops',
            'slug' => 'san-ops',
            'address' => '99 Cau Giay, Ha Noi',
            'status' => 'published',
            'is_active' => true,
        ]);

        $plan = PlatformPlan::create(['code' => 'POPS', 'name' => 'Operational Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Operational Court', 'slug' => 'operational-court', 'is_active' => true]);
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => 1,
            'starts_at' => '06:00',
            'ends_at' => '22:00',
            'price' => 100000,
            'is_active' => true,
        ]);
        $this->addPolicy($venue, $owner->id);
        $this->addOperatingSchedule($venue, $owner->id);
        $this->selectVerifiedReceiver($venue, $owner);

        Sanctum::actingAs($owner);

        // Immediate temporary closure (e.g. rain/maintenance)
        $closeRes = $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/operational-status", [
            'status' => 'temporarily_closed',
            'reason' => 'Bảo trì hệ thống mặt sân sau bão.',
        ]);

        $closeRes->assertStatus(200);
        $fresh = $venue->fresh();
        $this->assertEquals('temporarily_closed', $fresh->status);
        $this->assertFalse($fresh->is_active);
        $this->assertEquals('Bảo trì hệ thống mặt sân sau bão.', $fresh->status_reason);

        // Immediate reopen
        $reopenRes = $this->postJson("/api/partner/organizations/{$org->id}/venues/{$venue->id}/operational-status", [
            'status' => 'published',
        ]);

        $reopenRes->assertStatus(200);
        $fresh2 = $venue->fresh();
        $this->assertEquals('published', $fresh2->status);
        $this->assertTrue($fresh2->is_active);
        $this->assertNull($fresh2->status_reason);
    }

    private function selectVerifiedReceiver(Venue $venue, User $owner): void
    {
        $connection = new ReceivingConnection([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->id,
            'owner_user_id' => $owner->id,
            'provider' => 'sepay',
            'routing_id' => (string) Str::uuid(),
            'bank_code' => 'MBBank',
            'status' => ReceivingConnection::STATUS_VERIFIED,
            'credential_version' => 1,
            'owner_attested_at' => now(),
            'verified_at' => now(),
            'effective_from' => now(),
        ]);
        $connection->setAccountDetails('Venue Owner', '0123456789');
        $connection->webhook_secret_ciphertext = Crypt::encryptString('verified-test-secret');
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();
    }
}
