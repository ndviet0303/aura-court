<?php

namespace Tests\Feature\Support;

use App\Models\DisputeCase;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeTenantAndPartyScopingTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomerUser(string $email, string $name = 'User'): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role' => 'customer',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
    }

    private function createTenant(string $name, string $slug): array
    {
        $org = Organization::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $owner = $this->createCustomerUser("owner_{$slug}@test.local", "Owner {$name}");
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => "Venue {$name}",
            'slug' => "venue-{$slug}",
            'is_active' => true,
        ]);

        return [$org, $owner, $venue];
    }

    private function createDispute(Organization $org, Venue $venue, User $complainant, User $respondent, string $code = 'DSP-TEST-1'): DisputeCase
    {
        return DisputeCase::create([
            'dispute_code' => $code,
            'organization_id' => $org->id,
            'venue_id' => $venue->id,
            'court_name' => 'Sân 1',
            'title' => "Tranh chấp cơ sở {$venue->name}",
            'summary' => 'Tóm tắt tranh chấp kiểm thử',
            'description' => 'Mô tả tranh chấp kiểm thử',
            'complainant_id' => $complainant->id,
            'complainant_name' => $complainant->name,
            'respondent_id' => $respondent->id,
            'respondent_name' => $respondent->name,
            'facility_name' => $venue->name,
            'status' => 'pending',
            'severity' => 'medium',
            'dispute_type' => 'court_quality',
        ]);
    }

    /**
     * Scenario: Foreign case
     * When a principal supplies a case identifier outside permitted organization/party/reviewer scope,
     * list/detail/export/download/mutation SHALL deny access without disclosing case data.
     */
    public function test_tenant_owner_cannot_view_foreign_organization_dispute_detail(): void
    {
        [$orgA, $ownerA, $venueA] = $this->createTenant('Tenant Alpha', 'tenant-alpha');
        [$orgB, $ownerB, $venueB] = $this->createTenant('Tenant Beta', 'tenant-beta');

        $user1 = $this->createCustomerUser('customer1@test.local', 'Customer 1');
        $user2 = $this->createCustomerUser('customer2@test.local', 'Customer 2');

        $disputeAlpha = $this->createDispute($orgA, $venueA, $user1, $user2, 'DSP-ALPHA-01');

        // Owner of Tenant Beta attempts to view Tenant Alpha's dispute
        $res = $this->actingAs($ownerB)->getJson("/api/v1/disputes/{$disputeAlpha->id}");
        // MUST be 404 without disclosing case data
        $res->assertStatus(404);
        $res->assertJsonPath('message', 'Hồ sơ tranh chấp không tồn tại.');
    }

    /**
     * Scenario: Foreign mutation
     * Tenant owner cannot arbitrate foreign dispute
     */
    public function test_tenant_owner_cannot_arbitrate_foreign_organization_dispute(): void
    {
        [$orgA, $ownerA, $venueA] = $this->createTenant('Tenant Alpha', 'tenant-alpha');
        [$orgB, $ownerB, $venueB] = $this->createTenant('Tenant Beta', 'tenant-beta');

        $user1 = $this->createCustomerUser('customer1@test.local', 'Customer 1');
        $user2 = $this->createCustomerUser('customer2@test.local', 'Customer 2');

        $disputeAlpha = $this->createDispute($orgA, $venueA, $user1, $user2, 'DSP-ALPHA-02');

        // Owner of Tenant Beta attempts to arbitrate Tenant Alpha's dispute
        $res = $this->actingAs($ownerB)->postJson("/api/v1/disputes/{$disputeAlpha->id}/arbitrate", [
            'decision' => 'resolve',
            'notes' => 'Hacked resolution',
        ]);

        $res->assertStatus(404);

        // Dispute remains pending in DB
        $this->assertSame('pending', $disputeAlpha->fresh()->status);
    }

    /**
     * Listing scoping: Owner only sees disputes of their own organization
     */
    public function test_tenant_owner_dispute_queue_is_strictly_scoped_to_own_organization(): void
    {
        [$orgA, $ownerA, $venueA] = $this->createTenant('Tenant Alpha', 'tenant-alpha');
        [$orgB, $ownerB, $venueB] = $this->createTenant('Tenant Beta', 'tenant-beta');

        $user1 = $this->createCustomerUser('c1@test.local', 'User 1');
        $user2 = $this->createCustomerUser('c2@test.local', 'User 2');

        $disputeA = $this->createDispute($orgA, $venueA, $user1, $user2, 'DSP-AAA-01');
        $disputeB = $this->createDispute($orgB, $venueB, $user1, $user2, 'DSP-BBB-01');

        // Owner Alpha lists disputes
        $resA = $this->actingAs($ownerA)->getJson('/api/v1/disputes');

        $codesA = collect($resA->json('data'))->pluck('dispute_code')->toArray();
        $this->assertContains('DSP-AAA-01', $codesA);
        $this->assertNotContains('DSP-BBB-01', $codesA);

        // Owner Beta lists disputes
        $resB = $this->actingAs($ownerB)->getJson('/api/v1/disputes');

        $codesB = collect($resB->json('data'))->pluck('dispute_code')->toArray();
        $this->assertContains('DSP-BBB-01', $codesB);
        $this->assertNotContains('DSP-AAA-01', $codesB);
    }

    /**
     * Customer scoping: Customer only sees disputes where they are party (complainant or respondent)
     */
    public function test_customer_only_sees_disputes_where_they_are_complainant_or_respondent(): void
    {
        [$orgA, $ownerA, $venueA] = $this->createTenant('Tenant Alpha', 'tenant-alpha');

        $alice = $this->createCustomerUser('alice@test.local', 'Alice');
        $bob = $this->createCustomerUser('bob@test.local', 'Bob');
        $carol = $this->createCustomerUser('carol@test.local', 'Carol');

        // Dispute between Alice & Bob
        $disputeAlice = $this->createDispute($orgA, $venueA, $alice, $bob, 'DSP-ALICE-BOB');
        // Dispute between Bob & Carol
        $disputeCarol = $this->createDispute($orgA, $venueA, $bob, $carol, 'DSP-BOB-CAROL');

        // Alice lists disputes: only sees dispute where she is complainant
        $aliceList = $this->actingAs($alice)->getJson('/api/v1/disputes');
        $aliceCodes = collect($aliceList->json('data'))->pluck('dispute_code')->toArray();
        $this->assertContains('DSP-ALICE-BOB', $aliceCodes);
        $this->assertNotContains('DSP-BOB-CAROL', $aliceCodes);

        // Alice can view her own dispute
        $this->actingAs($alice)->getJson("/api/v1/disputes/{$disputeAlice->id}")->assertOk();

        // Alice CANNOT view Bob & Carol's dispute
        $this->actingAs($alice)->getJson("/api/v1/disputes/{$disputeCarol->id}")->assertStatus(404);
    }

    /**
     * Stats scoping: Tenant owner only sees dispute stats for their own organization
     */
    public function test_dispute_stats_are_scoped_by_tenant(): void
    {
        [$orgA, $ownerA, $venueA] = $this->createTenant('Tenant Alpha', 'tenant-alpha');
        [$orgB, $ownerB, $venueB] = $this->createTenant('Tenant Beta', 'tenant-beta');

        $u1 = $this->createCustomerUser('u1@test.local');
        $u2 = $this->createCustomerUser('u2@test.local');

        // Org A has 2 pending disputes
        $this->createDispute($orgA, $venueA, $u1, $u2, 'DSP-A1');
        $this->createDispute($orgA, $venueA, $u1, $u2, 'DSP-A2');

        // Org B has 3 pending disputes
        $this->createDispute($orgB, $venueB, $u1, $u2, 'DSP-B1');
        $this->createDispute($orgB, $venueB, $u1, $u2, 'DSP-B2');
        $this->createDispute($orgB, $venueB, $u1, $u2, 'DSP-B3');

        // Owner A stats: pending_count MUST be 2
        $resA = $this->actingAs($ownerA)->getJson('/api/v1/disputes/stats');
        $resA->assertOk();
        $this->assertSame(2, $resA->json('stats.pending_count'));

        // Owner B stats: pending_count MUST be 3
        $resB = $this->actingAs($ownerB)->getJson('/api/v1/disputes/stats');
        $resB->assertOk();
        $this->assertSame(3, $resB->json('stats.pending_count'));
    }

    /**
     * Platform admin has global access across all tenants
     */
    public function test_platform_admin_has_global_access_and_filtering(): void
    {
        [$orgA, $ownerA, $venueA] = $this->createTenant('Tenant Alpha', 'tenant-alpha');
        [$orgB, $ownerB, $venueB] = $this->createTenant('Tenant Beta', 'tenant-beta');

        $u1 = $this->createCustomerUser('u1@test.local');
        $u2 = $this->createCustomerUser('u2@test.local');

        $this->createDispute($orgA, $venueA, $u1, $u2, 'DSP-A-GLOBAL');
        $this->createDispute($orgB, $venueB, $u1, $u2, 'DSP-B-GLOBAL');

        $admin = User::factory()->create(['role' => 'admin']);

        // Platform admin sees both disputes in general list
        $res = $this->actingAs($admin)->getJson('/api/v1/admin/disputes');
        $res->assertOk();
        $codes = collect($res->json('data'))->pluck('dispute_code')->toArray();
        $this->assertContains('DSP-A-GLOBAL', $codes);
        $this->assertContains('DSP-B-GLOBAL', $codes);

        // Filter by organization_id = orgA
        $resFiltered = $this->actingAs($admin)->getJson("/api/v1/admin/disputes?organization_id={$orgA->id}");
        $resFiltered->assertOk();
        $filteredCodes = collect($resFiltered->json('data'))->pluck('dispute_code')->toArray();
        $this->assertContains('DSP-A-GLOBAL', $filteredCodes);
        $this->assertNotContains('DSP-B-GLOBAL', $filteredCodes);
    }
}
