<?php

namespace Tests\Feature\Subscription;

use App\Domain\Subscription\Actions\ConfirmSubscriptionPayment;
use App\Domain\Subscription\Actions\FailSubscriptionPayment;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformSubscriptionRenewalAndCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_term_end_cancellation_disables_auto_renew_while_preserving_active_period(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Cancellation Club',
            'slug' => 'cancellation-club',
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
            'code' => 'ACTIVE_P',
            'name' => 'Active Plan',
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        $futureEnd = now()->addDays(20);
        $sub = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => $futureEnd,
            'auto_renew' => true,
        ]);

        Sanctum::actingAs($owner);

        // Cancel renewal
        $res = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/cancel-renewal", [
            'reason' => 'Không có nhu cầu tiếp tục tháng sau.',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('data.auto_renew', false)
            ->assertJsonPath('data.status', 'active') // Status REMAINS active!
            ->assertJsonPath('data.is_valid', true); // Entitlement preserved!

        $fresh = $sub->fresh();
        $this->assertFalse($fresh->auto_renew);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertEquals('active', $fresh->status);
        $this->assertTrue($fresh->isValid());
        $this->assertEquals($futureEnd->toIso8601String(), $fresh->ends_at->toIso8601String());
    }

    public function test_failed_renewal_attempt_preserves_current_paid_period(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create([
            'code' => 'RENEW_P',
            'name' => 'Plan',
            'price' => 500000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        $currentEndsAt = now()->addDays(12);
        $sub = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDays(18),
            'ends_at' => $currentEndsAt,
            'auto_renew' => true,
        ]);

        Sanctum::actingAs($owner);

        // Initiate renewal payment
        $renewRes = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/renew");
        $renewRes->assertStatus(201);
        $refCode = $renewRes->json('data.reference_code');

        // Renewal payment attempt fails (e.g. card declined / cancelled by user)
        $failAction = app(FailSubscriptionPayment::class);
        $failedAttempt = $failAction->execute($refCode, 'Thẻ bị từ chối');

        $this->assertEquals('failed', $failedAttempt->status);

        // The current subscription MUST remain active and unchanged!
        $freshSub = $sub->fresh();
        $this->assertEquals('active', $freshSub->status);
        $this->assertTrue($freshSub->isValid());
        $this->assertEquals($currentEndsAt->toIso8601String(), $freshSub->ends_at->toIso8601String());
    }

    public function test_successful_renewal_extends_subscription_duration(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org', 'status' => 'active', 'partner_status' => 'approved']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);

        $plan = PlatformPlan::create([
            'code' => 'EXTEND_P',
            'name' => 'Plan',
            'price' => 500000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);

        $currentEndsAt = now()->addDays(10);
        $sub = PlatformSubscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDays(20),
            'ends_at' => $currentEndsAt,
            'auto_renew' => true,
        ]);

        Sanctum::actingAs($owner);

        $renewRes = $this->postJson("/api/v1/partner/organizations/{$org->id}/subscription/renew");
        $refCode = $renewRes->json('data.reference_code');

        // Confirm renewal payment
        $confirmAction = app(ConfirmSubscriptionPayment::class);
        $result = $confirmAction->execute(
            referenceCode: $refCode,
            providerTransactionId: 'TXN-RENEW-SUCCESS-01',
            amount: 500000,
            currency: 'VND'
        );

        $freshSub = $sub->fresh();
        $this->assertEquals('active', $freshSub->status);
        $this->assertTrue($freshSub->isValid());

        // Expected end date extended by 30 days from currentEndsAt: ~40 days from now!
        $this->assertTrue($freshSub->ends_at->greaterThan(now()->addDays(35)));
    }
}
