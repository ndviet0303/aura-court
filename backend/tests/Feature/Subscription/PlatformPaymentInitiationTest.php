<?php

namespace Tests\Feature\Subscription;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformPaymentInitiationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_initiates_payment_creates_pending_attempt_without_early_entitlement(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Paid Club',
            'slug' => 'paid-club',
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
            'code' => 'PREMIUM_MONTHLY',
            'name' => 'Gói Premium Hàng Tháng',
            'version' => 1,
            'price' => 1200000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'trial_enabled' => false,
            'max_venues' => 3,
            'max_courts' => 15,
            'max_staff' => 10,
            'terms_version' => '2026.PAID',
            'terms_text' => 'Điều khoản thanh toán',
            'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        // Initiate payment
        $res = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/initiate-payment", [
            'plan_id' => $plan->id,
            'accepted_terms_version' => '2026.PAID',
            'return_url' => 'https://app.auracourt.vn/partner/subscription/callback?status=success',
            'cancel_url' => 'https://app.auracourt.vn/partner/subscription/callback?status=cancel',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.amount', 1200000)
            ->assertJsonPath('data.currency', 'VND')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure(['data' => ['reference_code', 'checkout_url', 'provider']]);

        $refCode = $res->json('data.reference_code');
        $this->assertStringStartsWith('SUB-PAY-', $refCode);

        // 1. Payment attempt is created with pending status
        $this->assertDatabaseHas('platform_payment_attempts', [
            'organization_id' => $org->id,
            'reference_code' => $refCode,
            'amount' => 1200000,
            'status' => 'pending',
        ]);

        // 2. Entitlement is NOT yet granted before verification!
        $this->assertEquals(0, PlatformSubscription::where('organization_id', $org->id)->count());

        $currentRes = $this->getJson("/api/v1/partner/organizations/{$org->id}/subscription/current");
        $currentRes->assertStatus(200)
            ->assertJsonPath('has_active_subscription', false);
    }

    public function test_non_owner_cannot_initiate_payment(): void
    {
        $nonOwner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);

        $plan = PlatformPlan::create([
            'code' => 'PLAN',
            'name' => 'Plan',
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        Sanctum::actingAs($nonOwner);

        $res = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/initiate-payment", [
            'plan_id' => $plan->id,
            'accepted_terms_version' => '1.0',
        ]);

        $res->assertStatus(403);
    }
}
