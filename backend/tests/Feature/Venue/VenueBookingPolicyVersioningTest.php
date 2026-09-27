<?php

namespace Tests\Feature\Venue;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VenueBookingPolicyVersioningTest extends TestCase
{
    use RefreshDatabase;

    private function createOwnedVenue(): array
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $organization = Organization::create([
            'name' => 'Policy Club',
            'slug' => 'policy-club',
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => ['vi' => 'Sân Chính Sách', 'en' => 'Policy Court'],
            'slug' => 'policy-court',
            'address' => '123 Test Street',
            'status' => 'draft',
            'is_active' => false,
        ]);

        return [$owner, $organization, $venue];
    }

    private function policySettings(): array
    {
        return [
            'booking' => [
                'hold_ttl_minutes' => 15,
                'max_days_ahead' => 60,
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 240,
                'require_continuity' => true,
            ],
            'cancellation' => [
                'deadline_hours' => 24,
                'fee_type' => 'none',
                'fee_value' => 0,
                'refund_due_hours' => 48,
            ],
            'change' => [
                'deadline_hours' => 12,
                'fee_type' => 'percentage',
                'fee_value' => 10,
            ],
            'arrival' => [
                'early_minutes' => 15,
                'late_grace_minutes' => 10,
                'no_show_after_minutes' => 30,
            ],
            'recurring' => [
                'payment_policy' => 'per_period',
                'conflict_policy' => 'all_or_nothing',
                'overdue_release_hours' => 24,
            ],
            'transfer' => [
                'cutoff_hours' => 2,
                'max_consideration_percent' => 100,
                'free_transfer_allowed' => false,
                'seller_reimbursement_required' => true,
            ],
            'ancillary_credit_policy' => 'refund',
        ];
    }

    public function test_owner_saves_complete_policy_versions_and_readiness_requires_one(): void
    {
        [$owner, $organization, $venue] = $this->createOwnedVenue();
        Sanctum::actingAs($owner);
        $prefix = "/api/v1/partner/organizations/{$organization->id}/venues/{$venue->id}";

        $this->getJson("{$prefix}/readiness")
            ->assertOk()
            ->assertJsonPath('data.checklist.policies_configured', false)
            ->assertJsonFragment(['code' => 'BOOKING_POLICY_REQUIRED']);

        $this->getJson('/api/v1/admin/venues')
            ->assertOk()
            ->assertJsonFragment(['id' => $venue->id, 'status' => 'draft']);
        $this->patchJson("/api/v1/admin/venues/{$venue->id}", ['is_active' => true])
            ->assertStatus(409);

        $settings = $this->policySettings();
        $this->putJson("{$prefix}/booking-policy", ['settings' => $settings])
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.settings.cancellation.fee_type', 'none')
            ->assertJsonPath('data.settings.cancellation.fee_value', 0);

        $settings['booking']['max_days_ahead'] = 90;
        $this->putJson("{$prefix}/booking-policy", ['settings' => $settings])
            ->assertCreated()
            ->assertJsonPath('data.version', 2);

        $this->assertSame(2, VenueBookingPolicy::where('venue_id', $venue->id)->count());
        $this->assertSame(60, VenueBookingPolicy::where('venue_id', $venue->id)->where('version', 1)->firstOrFail()->settings['booking']['max_days_ahead']);
        $this->getJson("{$prefix}/booking-policy")
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.settings.booking.max_days_ahead', 90);
        $this->getJson("{$prefix}/readiness")
            ->assertOk()
            ->assertJsonPath('data.checklist.policies_configured', true)
            ->assertJsonMissing(['code' => 'BOOKING_POLICY_REQUIRED']);
    }

    public function test_incomplete_or_invalid_policy_does_not_create_a_version(): void
    {
        [$owner, $organization, $venue] = $this->createOwnedVenue();
        Sanctum::actingAs($owner);
        $url = "/api/v1/partner/organizations/{$organization->id}/venues/{$venue->id}/booking-policy";

        $this->putJson($url, ['settings' => ['booking' => ['hold_ttl_minutes' => 15]]])
            ->assertUnprocessable();

        $settings = $this->policySettings();
        $settings['booking']['min_duration_minutes'] = 300;
        $settings['cancellation']['fee_type'] = 'percentage';
        $settings['cancellation']['fee_value'] = 101;
        $this->putJson($url, ['settings' => $settings])
            ->assertUnprocessable();

        $this->assertSame(0, VenueBookingPolicy::where('venue_id', $venue->id)->count());
    }

    public function test_policy_reads_and_writes_are_scoped_to_venue_owners(): void
    {
        [$owner, $organization, $venue] = $this->createOwnedVenue();
        $stranger = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($stranger);
        $url = "/api/v1/partner/organizations/{$organization->id}/venues/{$venue->id}/booking-policy";

        $this->getJson($url)->assertForbidden();
        $this->putJson($url, ['settings' => $this->policySettings()])->assertForbidden();
        $this->assertSame(0, VenueBookingPolicy::where('venue_id', $venue->id)->count());
    }

    public function test_owner_venue_form_route_creates_a_non_public_draft_within_quota(): void
    {
        [$owner, $organization] = $this->createOwnedVenue();
        $plan = PlatformPlan::create([
            'code' => 'POLICY-PLAN',
            'name' => 'Policy Plan',
            'price' => 500000,
            'billing_cycle' => 'monthly',
            'max_venues' => 2,
            'max_courts' => 4,
            'terms_version' => '1.0',
            'terms_text' => 'Terms',
            'is_active' => true,
        ]);
        PlatformSubscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'plan_version' => 1,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/admin/venues', [
            'name' => 'New Policy Venue',
            'address' => '456 Test Street',
            'google_maps_url' => 'https://maps.google.com/maps?q=456+Test+Street',
        ])->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('venues', [
            'organization_id' => $organization->id,
            'name->en' => 'New Policy Venue',
            'status' => 'draft',
            'is_active' => false,
        ]);
    }
}
