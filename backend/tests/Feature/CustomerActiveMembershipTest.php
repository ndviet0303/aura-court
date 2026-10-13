<?php

namespace Tests\Feature;

use App\Models\MembershipEntitlement;
use App\Models\Organization;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerActiveMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_returns_the_active_plan_name_and_entitled_discount(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Membership Org', 'slug' => 'membership-org-'.uniqid()]);
        $this->membership($org, $user, 'Diamond VIP Club', now()->subMonths(2), now()->subDay());
        $active = $this->membership($org, $user, 'Gold Elite All-Access', now()->subDay(), now()->addMonth());
        MembershipEntitlement::create([
            'organization_id' => $org->id,
            'membership_id' => $active->id,
            'benefit_code' => 'discount_percent',
            'benefit_value' => ['percent' => 15],
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        // The profile page sends no organization header, so the endpoint must resolve the membership's tenant itself.
        $this->actingAs($user)->getJson('/api/v1/membership')
            ->assertOk()
            ->assertJsonPath('membership.id', $active->id)
            ->assertJsonPath('membership.organization_id', $org->id)
            ->assertJsonPath('membership.status', 'active')
            ->assertJsonPath('membership.plan.name', 'Gold Elite All-Access')
            ->assertJsonPath('membership.plan.discount_percent', 15);
    }

    public function test_membership_is_null_when_the_customer_has_no_active_plan(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/membership')
            ->assertOk()
            ->assertExactJson(['membership' => null]);

        $org = Organization::create(['name' => 'Expired Membership Org', 'slug' => 'expired-membership-org-'.uniqid()]);
        $this->membership($org, $user, 'Gold Elite All-Access', now()->subMonths(2), now()->subDay());

        $this->actingAs($user)->getJson('/api/v1/membership')
            ->assertOk()
            ->assertExactJson(['membership' => null]);
        $this->actingAs($user)->withHeaders(['X-Organization-ID' => (string) $org->id])->getJson('/api/v1/membership')
            ->assertOk()
            ->assertExactJson(['membership' => null]);
    }

    private function membership(Organization $org, User $user, string $planName, CarbonInterface $startsAt, CarbonInterface $endsAt): TenantMembership
    {
        $plan = TenantMembershipPlan::create([
            'organization_id' => $org->id,
            'name' => $planName,
            'version' => 1,
            'price' => 299000,
            'benefits' => [],
            'status' => 'published',
            'published_at' => now(),
        ]);

        return TenantMembership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
