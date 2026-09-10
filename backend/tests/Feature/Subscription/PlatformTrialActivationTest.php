<?php

namespace Tests\Feature\Subscription;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformTrialActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_owner_activates_trial_with_persisted_period_and_entitlement(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Trial Arena',
            'slug' => 'trial-arena',
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
            'code' => 'TRIAL_PLAN',
            'name' => 'Gói Dùng Thử 14 Ngày',
            'version' => 1,
            'price' => 300000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'trial_days' => 14,
            'trial_enabled' => true,
            'max_venues' => 1,
            'max_courts' => 4,
            'max_staff' => 2,
            'terms_version' => '2026.TRIAL',
            'terms_text' => 'Điều khoản dùng thử 14 ngày.',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        // Activate trial
        $res = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/activate-trial", [
            'plan_id' => $plan->id,
            'accepted_terms_version' => '2026.TRIAL',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.status', 'trial')
            ->assertJsonPath('data.is_valid', true)
            ->assertJsonPath('data.is_trial', true)
            ->assertJsonPath('data.plan_code', 'TRIAL_PLAN')
            ->assertJsonPath('data.trial_days_remaining', 14);

        $this->assertDatabaseHas('platform_subscriptions', [
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
        ]);

        // Current status reflects active trial and trial used
        $currentRes = $this->getJson("/api/v1/partner/organizations/{$org->id}/subscription/current");
        $currentRes->assertStatus(200)
            ->assertJsonPath('has_active_subscription', true)
            ->assertJsonPath('has_used_trial', true);
    }

    public function test_replay_trial_activation_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Replay Arena',
            'slug' => 'replay-arena',
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
            'code' => 'TRIAL_PLAN_2',
            'name' => 'Gói Dùng Thử 7 Ngày',
            'version' => 1,
            'price' => 300000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'trial_days' => 7,
            'trial_enabled' => true,
            'terms_version' => '2026.TRIAL',
            'terms_text' => 'Điều khoản',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        // First trial activation succeeds
        $res1 = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/activate-trial", [
            'plan_id' => $plan->id,
            'accepted_terms_version' => '2026.TRIAL',
        ]);
        $res1->assertStatus(201);

        // Second trial activation (replay) MUST be rejected!
        $res2 = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/activate-trial", [
            'plan_id' => $plan->id,
            'accepted_terms_version' => '2026.TRIAL',
        ]);
        $res2->assertStatus(422)
            ->assertJsonValidationErrors(['trial']);
    }

    public function test_cannot_activate_trial_on_plan_without_trial(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $paidOnlyPlan = PlatformPlan::create([
            'code' => 'PAID_ONLY',
            'name' => 'Gói Trả Phí Không Dùng Thử',
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'trial_enabled' => false, // No trial!
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        $res = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/activate-trial", [
            'plan_id' => $paidOnlyPlan->id,
            'accepted_terms_version' => '1.0',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['plan']);
    }

    public function test_non_owner_cannot_activate_trial(): void
    {
        $nonOwner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);

        $plan = PlatformPlan::create([
            'code' => 'TRIAL',
            'name' => 'Plan',
            'price' => 100000,
            'billing_cycle' => 'monthly',
            'trial_enabled' => true,
            'trial_days' => 14,
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        Sanctum::actingAs($nonOwner);

        $res = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/activate-trial", [
            'plan_id' => $plan->id,
            'accepted_terms_version' => '1.0',
        ]);

        $res->assertStatus(403);
    }
}
