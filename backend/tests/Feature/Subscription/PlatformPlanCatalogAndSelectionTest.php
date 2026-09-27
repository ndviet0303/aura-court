<?php

namespace Tests\Feature\Subscription;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformPlanCatalogAndSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_endpoint_discloses_fees_terms_and_quotas_for_active_plans(): void
    {
        // Active plan
        PlatformPlan::create([
            'code' => 'PRO_MONTHLY',
            'name' => 'Gói Pro Hàng Tháng',
            'version' => 1,
            'price' => 500000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'trial_days' => 14,
            'trial_enabled' => true,
            'max_venues' => 2,
            'max_courts' => 10,
            'max_staff' => 5,
            'features' => ['online_booking', 'pos', 'advanced_analytics'],
            'terms_version' => '2026.1',
            'terms_text' => 'Điều khoản gói Pro 2026.1: Phí dịch vụ 500.000đ/tháng.',
            'is_active' => true,
        ]);

        // Inactive plan
        PlatformPlan::create([
            'code' => 'DEPRECATED_PLAN',
            'name' => 'Gói Cũ',
            'version' => 1,
            'price' => 200000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'terms_version' => '2025.1',
            'terms_text' => 'Gói cũ',
            'is_active' => false,
        ]);

        $res = $this->getJson('/api/v1/platform/plans');
        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'PRO_MONTHLY')
            ->assertJsonPath('data.0.price', 500000)
            ->assertJsonPath('data.0.price_formatted', '500.000₫')
            ->assertJsonPath('data.0.currency', 'VND')
            ->assertJsonPath('data.0.billing_cycle', 'monthly')
            ->assertJsonPath('data.0.trial_days', 14)
            ->assertJsonPath('data.0.quotas.max_venues', 2)
            ->assertJsonPath('data.0.quotas.max_courts', 10)
            ->assertJsonPath('data.0.quotas.max_staff', 5)
            ->assertJsonPath('data.0.terms.version', '2026.1')
            ->assertJsonStructure(['data' => [['fee_disclosure' => ['tax_note', 'payment_processing_fee', 'refund_policy']]]]);
    }

    public function test_owner_can_select_plan_with_matching_terms_version(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Plan Selection Club',
            'slug' => 'plan-selection-club',
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
            'code' => 'STANDARD_YEARLY',
            'name' => 'Gói Standard Hàng Năm',
            'version' => 2,
            'price' => 4800000,
            'currency' => 'VND',
            'billing_cycle' => 'yearly',
            'trial_days' => 30,
            'trial_enabled' => true,
            'terms_version' => '2026.2',
            'terms_text' => 'Điều khoản gói Standard.',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        $res = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/select-plan", [
            'plan_id' => $plan->id,
            'accepted_terms_version' => '2026.2',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.organization_id', $org->id)
            ->assertJsonPath('data.plan_id', $plan->id)
            ->assertJsonPath('data.plan_version', 2)
            ->assertJsonPath('data.price', 4800000)
            ->assertJsonPath('data.billing_cycle', 'yearly')
            ->assertJsonPath('data.billing_months', 12)
            ->assertJsonPath('data.accepted_terms_version', '2026.2')
            ->assertJsonPath('data.trial_available', true);
    }

    public function test_six_month_calendar_term_is_supported_and_returned_in_plan_snapshot(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Six Month Club', 'slug' => 'six-month-club', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        $plan = PlatformPlan::create([
            'code' => 'SIX_MONTHS',
            'name' => 'Six Month Plan',
            'price' => 2800000,
            'billing_cycle' => 'six_months',
            'terms_version' => '2026.6',
            'terms_text' => 'Six calendar months.',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/select-plan", [
            'plan_id' => $plan->id,
            'accepted_terms_version' => '2026.6',
        ])->assertOk()
            ->assertJsonPath('data.billing_cycle', 'six_months')
            ->assertJsonPath('data.billing_months', 6);
    }

    public function test_selection_fails_with_missing_commercial_configuration(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        Sanctum::actingAs($owner);

        // 1. Missing price (0 without trial)
        $invalidPlan1 = PlatformPlan::create([
            'code' => 'INVALID_PRICE',
            'name' => 'Invalid Price Plan',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'trial_enabled' => false,
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        $res1 = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/select-plan", [
            'plan_id' => $invalidPlan1->id,
            'accepted_terms_version' => '1.0',
        ]);
        $res1->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);

        // 2. Missing billing cycle
        $invalidPlan2 = PlatformPlan::create([
            'code' => 'NO_CYCLE',
            'name' => 'No Cycle Plan',
            'price' => 100000,
            'billing_cycle' => '', // Missing!
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        $res2 = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/select-plan", [
            'plan_id' => $invalidPlan2->id,
            'accepted_terms_version' => '1.0',
        ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);

        // 3. Mismatched terms version
        $validPlan = PlatformPlan::create([
            'code' => 'VALID_P',
            'name' => 'Valid Plan',
            'price' => 100000,
            'billing_cycle' => 'monthly',
            'terms_version' => '2026.3',
            'terms_text' => 'Approved terms',
            'is_active' => true,
        ]);

        $res3 = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/select-plan", [
            'plan_id' => $validPlan->id,
            'accepted_terms_version' => '2025.0', // Stale/mismatched terms!
        ]);
        $res3->assertStatus(422)
            ->assertJsonValidationErrors(['accepted_terms_version']);
    }

    public function test_non_owner_cannot_select_plan(): void
    {
        $nonOwner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);

        $plan = PlatformPlan::create([
            'code' => 'P',
            'name' => 'Plan',
            'price' => 100000,
            'billing_cycle' => 'monthly',
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        Sanctum::actingAs($nonOwner);

        $res = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/select-plan", [
            'plan_id' => $plan->id,
            'accepted_terms_version' => '1.0',
        ]);

        $res->assertStatus(403);
    }
}
