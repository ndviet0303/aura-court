<?php

namespace Tests\Concerns;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Who may manage prices (design D25): the owner of the venue's organization (TestCase::ownerOf), and those who may
 * not, the venue's own staff manager and the owner of another organization. Each user belongs to one organization,
 * which the tenant context then picks without a header.
 */
trait PricingRoles
{
    /** A venue of a new active organization with a current platform plan. */
    protected function pricingVenue(string $name = 'Sân Cầu Lông Ánh Dương'): Venue
    {
        $organization = $this->pricingOrganization();
        $this->platformPlan($organization);

        return Venue::create(['organization_id' => $organization->id, 'name' => $name, 'slug' => 'pricing-'.Str::lower(Str::random(8)), 'is_active' => true]);
    }

    /** Staff assigned to the venue, so its manager for daily operations. */
    protected function staffOf(Venue $venue): User
    {
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $venue->organization_id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_STAFF, 'status' => 'active', 'permissions' => ['venue_ids' => [$venue->id]]]);

        return $user;
    }

    protected function ownerOfAnotherOrganization(): User
    {
        $organization = $this->pricingOrganization();
        $this->platformPlan($organization);

        return $this->ownerOf($organization);
    }

    protected function platformPlan(Organization $organization, ?CarbonInterface $endsAt = null): PlatformSubscription
    {
        $plan = PlatformPlan::create(['code' => 'PLAN-'.Str::upper(Str::random(6)), 'name' => 'Pricing Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);

        return PlatformSubscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => $endsAt ?? now()->addMonth(),
        ]);
    }

    private function pricingOrganization(): Organization
    {
        return Organization::create(['name' => 'Pricing Club', 'slug' => 'pricing-club-'.Str::lower(Str::random(8)), 'status' => 'active', 'partner_status' => 'approved']);
    }
}
