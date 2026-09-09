<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PointLedger;
use App\Models\TenantCustomer;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Models\WalletAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCustomerRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_customer_creation_and_guest_contact_persistence(): void
    {
        $org = Organization::create(['name' => 'Aura Arena', 'slug' => 'aura-arena-'.uniqid()]);
        $cashier = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $registeredUser = User::factory()->create([
            'name' => 'Nguyen Van A',
            'email' => 'vana@test.com',
            'phone' => '0901112222',
        ]);

        // 1. Create registered customer relationship
        $res1 = $this->actingAs($cashier)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson('/api/v2/customers', [
                'name' => 'Nguyen Van A (Customer)',
                'user_id' => $registeredUser->id,
                'phone' => '0901112222',
                'email' => 'vana@test.com',
                'notes' => 'Frequent evening badminton player',
            ]);

        $res1->assertStatus(201)
            ->assertJsonPath('customer.name', 'Nguyen Van A (Customer)')
            ->assertJsonPath('customer.user_id', $registeredUser->id)
            ->assertJsonPath('customer.is_guest', false)
            ->assertJsonPath('customer.phone', '0901112222');

        $this->assertDatabaseHas('tenant_customers', [
            'organization_id' => $org->id,
            'user_id' => $registeredUser->id,
            'is_guest' => false,
            'phone' => '0901112222',
        ]);

        // 2. Create guest contact persistence (user_id is null)
        $res2 = $this->actingAs($cashier)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson('/api/v2/customers', [
                'name' => 'Tran Thi Guest',
                'phone' => '0909998888',
                'email' => 'guest@example.com',
                'is_guest' => true,
                'notes' => 'Walk-in guest contact',
            ]);

        $res2->assertStatus(201)
            ->assertJsonPath('customer.name', 'Tran Thi Guest')
            ->assertJsonPath('customer.user_id', null)
            ->assertJsonPath('customer.is_guest', true)
            ->assertJsonPath('customer.phone', '0909998888');

        $this->assertDatabaseHas('tenant_customers', [
            'organization_id' => $org->id,
            'user_id' => null,
            'is_guest' => true,
            'phone' => '0909998888',
        ]);
    }

    public function test_guest_contact_does_not_fabricate_user_or_wallet(): void
    {
        $org = Organization::create(['name' => 'Aura Club', 'slug' => 'aura-club-'.uniqid()]);
        $cashier = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        $userCountBefore = User::count();
        $walletCountBefore = WalletAccount::count();

        $response = $this->actingAs($cashier)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson('/api/v2/customers', [
                'name' => 'Pure Walk-in Guest',
                'phone' => '0987654321',
                'is_guest' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('customer.user_id', null)
            ->assertJsonPath('customer.is_guest', true);

        // Assert no new User or WalletAccount record was fabricated
        $this->assertSame($userCountBefore, User::count());
        $this->assertSame($walletCountBefore, WalletAccount::count());
    }

    public function test_tenant_customer_uniqueness_within_same_tenant(): void
    {
        $org = Organization::create(['name' => 'Aura Sport', 'slug' => 'aura-sport-'.uniqid()]);
        $staff = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $staff->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        $user = User::factory()->create(['email' => 'player@aura.test']);

        // First customer record succeeds
        $this->actingAs($staff)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson('/api/v2/customers', [
                'name' => 'Player One',
                'user_id' => $user->id,
                'phone' => '0912345678',
            ])
            ->assertStatus(201);

        // Duplicate user_id in same tenant fails
        $dupUserRes = $this->actingAs($staff)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson('/api/v2/customers', [
                'name' => 'Player One Duplicate',
                'user_id' => $user->id,
                'phone' => '0912345679',
            ]);

        $dupUserRes->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        // Duplicate phone in same tenant fails
        $dupPhoneRes = $this->actingAs($staff)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson('/api/v2/customers', [
                'name' => 'Guest With Same Phone',
                'phone' => '0912345678',
                'is_guest' => true,
            ]);

        $dupPhoneRes->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_cross_tenant_customer_isolation_and_coexistence(): void
    {
        $orgA = Organization::create(['name' => 'Tenant A', 'slug' => 'tenant-a-'.uniqid()]);
        $orgB = Organization::create(['name' => 'Tenant B', 'slug' => 'tenant-b-'.uniqid()]);

        $staffA = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $orgA->id,
            'user_id' => $staffA->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        $staffB = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $orgB->id,
            'user_id' => $staffB->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        $sharedUser = User::factory()->create(['email' => 'shared@test.com']);
        $sharedPhone = '0933445566';

        // 1. Create customer relationship in Org A
        $this->actingAs($staffA)
            ->withHeader('X-Organization-ID', (string) $orgA->id)
            ->postJson('/api/v2/customers', [
                'name' => 'Shared Customer in Org A',
                'user_id' => $sharedUser->id,
                'phone' => $sharedPhone,
            ])
            ->assertStatus(201);

        // 2. Coexistence: same user and same phone in Org B succeeds without conflict
        $this->actingAs($staffB)
            ->withHeader('X-Organization-ID', (string) $orgB->id)
            ->postJson('/api/v2/customers', [
                'name' => 'Shared Customer in Org B',
                'user_id' => $sharedUser->id,
                'phone' => $sharedPhone,
            ])
            ->assertStatus(201);

        // 3. Isolation: Querying Org A returns only Org A's customer records
        $listResA = $this->actingAs($staffA)
            ->withHeader('X-Organization-ID', (string) $orgA->id)
            ->getJson('/api/v2/customers');

        $listResA->assertOk()
            ->assertJsonPath('organization_id', $orgA->id)
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'Shared Customer in Org A');

        // Isolation: Querying Org B returns only Org B's customer records
        $listResB = $this->actingAs($staffB)
            ->withHeader('X-Organization-ID', (string) $orgB->id)
            ->getJson('/api/v2/customers');

        $listResB->assertOk()
            ->assertJsonPath('organization_id', $orgB->id)
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'Shared Customer in Org B');
    }

    public function test_employee_memberships_are_kept_separate_from_customer_relationships(): void
    {
        $org = Organization::create(['name' => 'Aura Court Hub', 'slug' => 'aura-hub-'.uniqid()]);

        // Create an employee (manager)
        $manager = User::factory()->create(['name' => 'Manager Dave']);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $manager->id,
            'role' => OrganizationMember::ROLE_VENUE_MANAGER,
            'status' => 'active',
        ]);

        // 1. Employee is NOT present in tenant_customers
        $this->assertDatabaseMissing('tenant_customers', [
            'organization_id' => $org->id,
            'user_id' => $manager->id,
        ]);

        $listRes = $this->actingAs($manager)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->getJson('/api/v2/customers');

        $listRes->assertOk()
            ->assertJsonCount(0, 'customers');

        // 2. Now create a customer relationship for a separate player
        $player = User::factory()->create(['name' => 'Customer Alice']);
        $this->actingAs($manager)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson('/api/v2/customers', [
                'name' => 'Customer Alice',
                'user_id' => $player->id,
                'phone' => '0911223344',
            ])
            ->assertStatus(201);

        // 3. Customer Alice is NOT an employee member in organization_members
        $this->assertDatabaseMissing('organization_members', [
            'organization_id' => $org->id,
            'user_id' => $player->id,
        ]);

        // 4. Customer Alice cannot perform employee actions (e.g. creating customers)
        $this->actingAs($player)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson('/api/v2/customers', [
                'name' => 'Unauthorized Attempt',
                'phone' => '0999999999',
            ])
            ->assertStatus(403);
    }

    public function test_customer_search_filters_by_query_string(): void
    {
        $org = Organization::create(['name' => 'Aura Pickleball', 'slug' => 'aura-pb-'.uniqid()]);
        $owner = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        TenantCustomer::create([
            'organization_id' => $org->id,
            'name' => 'Le Van Cuong',
            'phone' => '0901234567',
            'email' => 'cuong@aura.test',
            'is_guest' => false,
            'status' => 'active',
        ]);

        TenantCustomer::create([
            'organization_id' => $org->id,
            'name' => 'Pham Thi Dung',
            'phone' => '0988776655',
            'email' => 'dung@aura.test',
            'is_guest' => true,
            'status' => 'active',
        ]);

        // Search by phone
        $resPhone = $this->actingAs($owner)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->getJson('/api/v2/customers?q=090123');

        $resPhone->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'Le Van Cuong');

        // Search by name
        $resName = $this->actingAs($owner)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->getJson('/api/v2/customers?q=Dung');

        $resName->assertOk()
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'Pham Thi Dung');

        // Search with no match
        $resEmpty = $this->actingAs($owner)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->getJson('/api/v2/customers?q=NonExistent');

        $resEmpty->assertOk()
            ->assertJsonCount(0, 'customers');
    }

    public function test_authorized_customer_lookup_returns_real_wallet_membership_and_loyalty_values(): void
    {
        $org = Organization::create(['name' => 'Aura Pro Hub', 'slug' => 'aura-pro-'.uniqid()]);
        $cashier = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $cashier->id,
            'role' => OrganizationMember::ROLE_CASHIER,
            'status' => 'active',
        ]);

        // 1. User A: Registered with active VIP membership, wallet balance, and loyalty points
        $userA = User::factory()->create(['name' => 'VIP Player A', 'phone' => '0911000001']);
        TenantCustomer::create([
            'organization_id' => $org->id,
            'user_id' => $userA->id,
            'name' => 'VIP Player A',
            'phone' => '0911000001',
            'is_guest' => false,
            'status' => 'active',
        ]);

        $vipPlan = TenantMembershipPlan::create([
            'organization_id' => $org->id,
            'name' => 'VIP Diamond',
            'version' => 1,
            'price' => 1000000,
            'benefits' => ['discount_percent' => 20],
            'status' => 'published',
            'published_at' => now(),
        ]);

        TenantMembership::create([
            'organization_id' => $org->id,
            'user_id' => $userA->id,
            'plan_id' => $vipPlan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);

        WalletAccount::create([
            'organization_id' => $org->id,
            'user_id' => $userA->id,
            'currency' => 'VND',
            'balance' => 350000,
            'status' => 'active',
        ]);

        PointLedger::create([
            'organization_id' => $org->id,
            'user_id' => $userA->id,
            'delta' => 500,
            'balance_after' => 500,
            'source_type' => 'manual',
            'source_id' => 1,
        ]);

        // 2. User B: Registered customer with NO membership, 0 wallet, 0 points
        $userB = User::factory()->create(['name' => 'Ordinary Player B', 'phone' => '0911000002']);
        TenantCustomer::create([
            'organization_id' => $org->id,
            'user_id' => $userB->id,
            'name' => 'Ordinary Player B',
            'phone' => '0911000002',
            'is_guest' => false,
            'status' => 'active',
        ]);

        // 3. Guest C: Walk-in guest without a user account
        TenantCustomer::create([
            'organization_id' => $org->id,
            'user_id' => null,
            'name' => 'Walkin Guest C',
            'phone' => '0911000003',
            'is_guest' => true,
            'status' => 'active',
        ]);

        // Query customers as cashier
        $response = $this->actingAs($cashier)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->getJson('/api/v2/customers');

        $response->assertOk()
            ->assertJsonCount(3, 'customers');

        $customers = collect($response->json('customers'));

        // Verify User A: Real VIP values
        $custA = $customers->firstWhere('name', 'VIP Player A');
        $this->assertNotNull($custA);
        $this->assertSame($userA->id, $custA['id']);
        $this->assertSame('VIP Diamond', $custA['tier']);
        $this->assertEquals(0.2, $custA['discount_rate']);
        $this->assertSame(350000, $custA['wallet_balance']);
        $this->assertSame(500, $custA['loyalty_points']);
        $this->assertFalse($custA['is_guest']);

        // Verify User B: Default standard values, no fabricated discounts or wallet
        $custB = $customers->firstWhere('name', 'Ordinary Player B');
        $this->assertNotNull($custB);
        $this->assertSame($userB->id, $custB['id']);
        $this->assertSame('Standard', $custB['tier']);
        $this->assertEquals(0.0, $custB['discount_rate']);
        $this->assertSame(0, $custB['wallet_balance']);
        $this->assertSame(0, $custB['loyalty_points']);
        $this->assertFalse($custB['is_guest']);

        // Verify Guest C: No fabricated user account or wallet
        $custC = $customers->firstWhere('name', 'Walkin Guest C');
        $this->assertNotNull($custC);
        $this->assertNull($custC['user_id']);
        $this->assertSame('Standard', $custC['tier']);
        $this->assertEquals(0.0, $custC['discount_rate']);
        $this->assertSame(0, $custC['wallet_balance']);
        $this->assertSame(0, $custC['loyalty_points']);
        $this->assertTrue($custC['is_guest']);
    }
}
