<?php

namespace Tests\Feature\Subscription;

use App\Domain\Admin\Actions\CreateVenue;
use App\Domain\Subscription\Actions\ActivatePlatformTrial;
use App\Domain\Subscription\Actions\ConfirmSubscriptionPayment;
use App\Domain\Subscription\Actions\EnforcePlanQuota;
use App\Domain\Subscription\Actions\SelectPlatformPlan;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformProviderReceipt;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlanQuotaEnforcementAndDowngradeTest extends TestCase
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

    private function createOrgWithPlan(string $name, string $slug, array $quotas = ['max_venues' => 1, 'max_courts' => 2, 'max_staff' => 2]): array
    {
        $org = Organization::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $owner = $this->createCustomerUser("owner_{$slug}@test.local", 'Owner');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $plan = PlatformPlan::create([
            'code' => strtoupper($slug).'_PLAN',
            'name' => "{$name} Plan",
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'terms_version' => '2026.1',
            'terms_text' => 'Terms and conditions',
            'max_venues' => $quotas['max_venues'] ?? 1,
            'max_courts' => $quotas['max_courts'] ?? 2,
            'max_staff' => $quotas['max_staff'] ?? 2,
            'is_active' => true,
        ]);

        $sub = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        return [$org, $owner, $plan, $sub];
    }

    /**
     * Requirement: Subscription contract and enforcement completeness
     * Court creation must enforce plan quotas atomically.
     */
    public function test_court_creation_enforces_plan_quota(): void
    {
        [$org, $owner, $plan] = $this->createOrgWithPlan('Alpha Court Org', 'alpha-court', [
            'max_venues' => 1,
            'max_courts' => 2, // Limit: 2 courts
            'max_staff' => 2,
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Alpha Venue',
            'slug' => 'alpha-venue',
            'is_active' => true,
        ]);

        // Court 1: Within quota
        $res1 = $this->actingAs($owner)->postJson('/api/v1/admin/courts', [
            'name' => 'Sân 1',
            'venue_id' => $venue->id,
            'is_active' => true,
        ]);
        $res1->assertStatus(201);

        // Court 2: Within quota (reaches limit 2)
        $res2 = $this->actingAs($owner)->postJson('/api/v1/admin/courts', [
            'name' => 'Sân 2',
            'venue_id' => $venue->id,
            'is_active' => true,
        ]);
        $res2->assertStatus(201);

        // Court 3: Exceeds quota (2 + 1 > 2) -> MUST fail with 422 validation error
        $res3 = $this->actingAs($owner)->postJson('/api/v1/admin/courts', [
            'name' => 'Sân 3',
            'venue_id' => $venue->id,
            'is_active' => true,
        ]);
        $res3->assertStatus(422);
        $res3->assertJsonValidationErrors(['quota']);

        // Exactly 2 courts exist
        $this->assertEquals(2, Court::where('venue_id', $venue->id)->count());
    }

    /**
     * Moving a court to a venue belonging to another organization with full court quota fails
     */
    public function test_moving_court_enforces_target_organization_quota(): void
    {
        [$orgA, $ownerA] = $this->createOrgWithPlan('Org A', 'org-a', ['max_courts' => 5]);
        [$orgB, $ownerB] = $this->createOrgWithPlan('Org B', 'org-b', ['max_courts' => 1]);

        $venueA = Venue::create(['organization_id' => $orgA->id, 'name' => 'Venue A', 'slug' => 'venue-a', 'is_active' => true]);
        $venueB = Venue::create(['organization_id' => $orgB->id, 'name' => 'Venue B', 'slug' => 'venue-b', 'is_active' => true]);

        // Org B already has 1 court (fills its quota of 1)
        Court::create(['name' => ['vi' => 'Sân B1'], 'venue_id' => $venueB->id, 'is_active' => true]);

        // Court in Org A
        $courtA = Court::create(['name' => ['vi' => 'Sân A1'], 'venue_id' => $venueA->id, 'is_active' => true]);

        // Admin of both or platform admin attempts to move Court A to Venue B
        $platformAdmin = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($platformAdmin)->patchJson("/api/v1/admin/courts/{$courtA->id}", [
            'venue_id' => $venueB->id,
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['quota']);
    }

    /**
     * Venue creation enforces plan quota
     */
    public function test_venue_creation_enforces_plan_quota(): void
    {
        [$org, $owner, $plan] = $this->createOrgWithPlan('Single Venue Org', 'single-venue', [
            'max_venues' => 1, // Limit: 1 venue
        ]);

        $createVenue = app(CreateVenue::class);

        // First venue: succeeds
        $venue1 = $createVenue->execute([
            'name' => 'Venue One',
            'organization_id' => $org->id,
        ]);
        $this->assertNotNull($venue1->id);

        // Second venue: must throw ValidationException
        $this->expectException(ValidationException::class);
        $createVenue->execute([
            'name' => 'Venue Two',
            'organization_id' => $org->id,
        ]);
    }

    /**
     * Updating member role or status to active staff enforces staff quota
     */
    public function test_updating_member_to_active_staff_enforces_quota(): void
    {
        [$org, $owner, $plan] = $this->createOrgWithPlan('Staff Limit Org', 'staff-limit', [
            'max_staff' => 1, // Limit: 1 staff
        ]);

        // 1 active staff member already exists
        $staff1 = $this->createCustomerUser('staff1@limit.local');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $staff1->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        // Suspended staff member
        $staff2 = $this->createCustomerUser('staff2@limit.local');
        $member2 = OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $staff2->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'suspended',
        ]);

        // Activating suspended staff2 would increase active staff count to 2 (> 1) -> MUST fail
        $res = $this->actingAs($owner)->patchJson("/api/v1/organizations/{$org->id}/members/{$member2->id}", [
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['quota']);
    }

    /**
     * Scenario: Unsupported plan change / downgrade exceeds target quotas
     * When an owner tries to select a plan whose quotas are smaller than existing resources,
     * it MUST fail atomically with a validation error.
     */
    public function test_downgrade_exceeding_target_quotas_is_rejected_on_plan_selection(): void
    {
        [$org, $owner] = $this->createOrgWithPlan('Big Club Org', 'big-club', [
            'max_venues' => 2,
            'max_courts' => 6,
            'max_staff' => 5,
        ]);

        // Club has 2 venues and 4 courts
        $venue1 = Venue::create(['organization_id' => $org->id, 'name' => 'Venue 1', 'slug' => 'v-1', 'is_active' => true]);
        $venue2 = Venue::create(['organization_id' => $org->id, 'name' => 'Venue 2', 'slug' => 'v-2', 'is_active' => true]);

        Court::create(['name' => ['vi' => 'S1'], 'venue_id' => $venue1->id, 'is_active' => true]);
        Court::create(['name' => ['vi' => 'S2'], 'venue_id' => $venue1->id, 'is_active' => true]);
        Court::create(['name' => ['vi' => 'S3'], 'venue_id' => $venue2->id, 'is_active' => true]);
        Court::create(['name' => ['vi' => 'S4'], 'venue_id' => $venue2->id, 'is_active' => true]);

        // Target cheaper plan with limit of 1 venue and 2 courts
        $cheapPlan = PlatformPlan::create([
            'code' => 'CHEAP_PLAN',
            'name' => 'Gói Giá Rẻ',
            'price' => 100000,
            'billing_cycle' => 'monthly',
            'terms_version' => '2026.1',
            'terms_text' => 'Terms and conditions',
            'max_venues' => 1, // Only 1 venue allowed
            'max_courts' => 2, // Only 2 courts allowed
            'max_staff' => 1,
            'is_active' => true,
        ]);

        $selector = app(SelectPlatformPlan::class);

        // Attempting to select this plan must fail due to venue and court count
        $this->expectException(ValidationException::class);
        $selector->execute($org->id, $cheapPlan->id, $owner->id, '2026.1');
    }

    /**
     * Activating trial on a plan with smaller quotas than existing resources is rejected
     */
    public function test_activate_trial_with_insufficient_quotas_is_rejected(): void
    {
        $org = Organization::create([
            'name' => 'Trial Org',
            'slug' => 'trial-org',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $owner = $this->createCustomerUser('trial_owner@test.local');
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        // Org has 3 venues
        Venue::create(['organization_id' => $org->id, 'name' => 'Venue A', 'slug' => 'va', 'is_active' => true]);
        Venue::create(['organization_id' => $org->id, 'name' => 'Venue B', 'slug' => 'vb', 'is_active' => true]);
        Venue::create(['organization_id' => $org->id, 'name' => 'Venue C', 'slug' => 'vc', 'is_active' => true]);

        // Trial plan allows only 1 venue
        $trialPlan = PlatformPlan::create([
            'code' => 'TRIAL_PLAN',
            'name' => 'Gói Dùng Thử',
            'price' => 0,
            'trial_enabled' => true,
            'trial_days' => 14,
            'billing_cycle' => 'monthly',
            'terms_version' => '2026.1',
            'terms_text' => 'Trial terms',
            'max_venues' => 1,
            'max_courts' => 2,
            'max_staff' => 2,
            'is_active' => true,
        ]);

        $activator = app(ActivatePlatformTrial::class);

        $this->expectException(ValidationException::class);
        $activator->execute($org->id, $trialPlan->id, $owner->id, '2026.1');
    }

    /**
     * Payment confirmation for plan change with insufficient quotas fails with 422
     */
    public function test_payment_confirmation_rejects_plan_change_exceeding_quotas(): void
    {
        [$org, $owner] = $this->createOrgWithPlan('Payment Org', 'payment-org', [
            'max_venues' => 3,
        ]);

        // Org has 2 venues
        Venue::create(['organization_id' => $org->id, 'name' => 'V1', 'slug' => 'v1', 'is_active' => true]);
        Venue::create(['organization_id' => $org->id, 'name' => 'V2', 'slug' => 'v2', 'is_active' => true]);

        // Target plan allows only 1 venue
        $smallPlan = PlatformPlan::create([
            'code' => 'SMALL_PLAN',
            'name' => 'Small Plan',
            'price' => 150000,
            'max_venues' => 1,
            'max_courts' => 4,
            'max_staff' => 2,
            'is_active' => true,
        ]);

        $attempt = PlatformPaymentAttempt::create([
            'organization_id' => $org->id,
            'reference_code' => 'REF-SMALL-PLAN-1',
            'provider' => 'sepay',
            'amount' => 150000,
            'currency' => 'VND',
            'status' => 'pending',
            'metadata' => [
                'plan_id' => $smallPlan->id,
                'billing_cycle' => 'monthly',
            ],
            'expires_at' => now()->addMinutes(15),
        ]);
        PlatformProviderReceipt::create([
            'payment_attempt_id' => $attempt->id,
            'provider' => $attempt->provider,
            'provider_transaction_id' => 'TX-SMALL-1',
            'amount' => 150000,
            'currency' => 'VND',
            'raw_payload' => ['verified_fixture' => true],
            'verified_at' => now(),
        ]);

        $confirmer = app(ConfirmSubscriptionPayment::class);

        $this->expectException(HttpException::class);
        $confirmer->execute(
            referenceCode: 'REF-SMALL-PLAN-1',
            providerTransactionId: 'TX-SMALL-1',
            amount: 150000,
            currency: 'VND'
        );
    }
}
