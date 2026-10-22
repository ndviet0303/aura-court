<?php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosOrder;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BR-89: aggregated finance reports belong to organization owners, scoped to the organizations they own.
 * Staff never see them, even for venues they are assigned to.
 */
class AdminReportOwnerScopeTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTES = ['/api/admin/reports', '/api/admin/reports.csv', '/api/admin/reports/csv'];

    private Venue $venueA;

    private Venue $venueB;

    private User $ownerAStaffB;

    private User $staffB;

    protected function setUp(): void
    {
        parent::setUp();

        [$orgA, $this->venueA] = $this->tenant('Org A', 50000, 'POS-SCOPE-A');
        [$orgB, $this->venueB] = $this->tenant('Org B', 70000, 'POS-SCOPE-B');

        $this->ownerAStaffB = $this->user('owner-a-staff-b@scope.local');
        $this->member($orgA, $this->ownerAStaffB, OrganizationMember::ROLE_ORGANIZATION_OWNER);
        $this->member($orgB, $this->ownerAStaffB, OrganizationMember::ROLE_STAFF, $this->venueB);

        $this->staffB = $this->user('staff-b@scope.local');
        $this->member($orgB, $this->staffB, OrganizationMember::ROLE_STAFF, $this->venueB);
    }

    public function test_staff_cannot_read_reports_even_for_their_assigned_venue(): void
    {
        foreach (self::ROUTES as $route) {
            $this->actingAs($this->staffB)->getJson($route)->assertForbidden();
            $this->actingAs($this->staffB)->getJson("{$route}?venue_id={$this->venueB->id}")->assertForbidden();
        }
    }

    public function test_user_without_membership_cannot_read_reports(): void
    {
        $outsider = $this->user('outsider@scope.local');

        foreach (self::ROUTES as $route) {
            $this->actingAs($outsider)->getJson($route)->assertForbidden();
        }
    }

    public function test_owner_sees_only_owned_organizations_not_ones_they_staff(): void
    {
        $this->actingAs($this->ownerAStaffB)->getJson('/api/admin/reports')
            ->assertOk()->assertJsonPath('pos_revenue', 50000);

        foreach (['/api/admin/reports.csv', '/api/admin/reports/csv'] as $route) {
            $csv = $this->actingAs($this->ownerAStaffB)->get($route)->assertOk()->streamedContent();
            $this->assertStringContainsString('POS-SCOPE-A', $csv);
            $this->assertStringNotContainsString('POS-SCOPE-B', $csv);
        }

        foreach (self::ROUTES as $route) {
            $this->actingAs($this->ownerAStaffB)->getJson("{$route}?venue_id={$this->venueB->id}")->assertForbidden();
        }
        $this->actingAs($this->ownerAStaffB)->getJson("/api/admin/reports?venue_id={$this->venueA->id}")
            ->assertOk()->assertJsonPath('pos_revenue', 50000);
    }

    public function test_platform_admin_sees_every_organization(): void
    {
        $admin = User::factory()->create(['email' => 'admin@scope.local', 'role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->getJson('/api/admin/reports')->assertOk()->assertJsonPath('pos_revenue', 120000);
        $this->actingAs($admin)->getJson("/api/admin/reports?venue_id={$this->venueB->id}")
            ->assertOk()->assertJsonPath('pos_revenue', 70000);
    }

    /** @return array{0: Organization, 1: Venue} */
    private function tenant(string $name, int $posTotal, string $posReference): array
    {
        $org = Organization::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $venue = Venue::create(['organization_id' => $org->id, 'name' => "{$name} Venue", 'slug' => str($name)->slug().'-venue-'.uniqid(), 'is_active' => true]);
        $cashier = $this->user(str($name)->slug().'-cashier@scope.local');

        PosOrder::create([
            'organization_id' => $org->id, 'venue_id' => $venue->id, 'cashier_id' => $cashier->id,
            'status' => 'completed', 'payment_method' => 'cash', 'subtotal' => $posTotal, 'total_amount' => $posTotal,
            'payment_reference' => $posReference, 'completed_at' => now(),
        ]);

        return [$org, $venue];
    }

    private function user(string $email): User
    {
        return User::factory()->create(['email' => $email, 'role' => 'customer', 'status' => 'active', 'email_verified_at' => now(), 'phone_verified_at' => now()]);
    }

    private function member(Organization $org, User $user, string $role, ?Venue $venue = null): void
    {
        OrganizationMember::create([
            'organization_id' => $org->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active',
            'permissions' => $venue ? ['venue_ids' => [$venue->id], 'functions' => ['reports']] : null,
        ]);
    }
}
