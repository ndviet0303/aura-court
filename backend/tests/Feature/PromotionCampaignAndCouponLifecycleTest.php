<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Court;
use App\Models\MembershipEntitlement;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\PromotionCampaign;
use App\Models\Reservation;
use App\Models\TenantMembership;
use App\Models\TenantMembershipPlan;
use App\Models\User;
use App\Models\Venue;
use App\Services\Booking\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromotionCampaignAndCouponLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_manages_coupon_campaign_lifecycle_audit_and_builtin_customer_groups(): void
    {
        $fixture = $this->tenant();
        $owner = $fixture['owner'];
        $organization = $fixture['organization'];
        $headers = ['X-Organization-ID' => (string) $organization->id];
        $startsAt = now()->subDay()->toIso8601String();
        $endsAt = now()->addDays(7)->toIso8601String();

        Sanctum::actingAs($owner);
        $created = $this->withHeaders($headers)->postJson('/api/admin/coupons', [
            'code' => 'first10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'min_spend' => 10000,
            'usage_limit' => 1,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'campaign_name' => 'First booking welcome',
            'eligibility_group' => 'first_time_customers',
            'campaign_usage_limit' => 1,
        ])->assertCreated()
            ->assertJsonPath('coupon.code', 'FIRST10')
            ->assertJsonPath('coupon.status', 'draft')
            ->assertJsonPath('coupon.campaign.status', 'draft')
            ->assertJsonPath('coupon.campaign.eligibility_group', 'first_time_customers');

        $couponId = (int) $created->json('coupon.id');
        $campaignId = (int) $created->json('coupon.promotion_campaign_id');
        $this->withHeaders($headers)->getJson('/api/admin/coupons')
            ->assertOk()->assertJsonPath('data.0.id', $couponId);

        $this->actingAs($fixture['newCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'FIRST10',
        ])->assertUnprocessable();

        Sanctum::actingAs($owner);
        $this->withHeaders($headers)->patchJson("/api/admin/coupons/{$couponId}", [
            'discount_value' => 15,
            'campaign_name' => 'Updated first booking welcome',
        ])->assertOk()
            ->assertJsonPath('coupon.version', 2)
            ->assertJsonPath('coupon.campaign.version', 2)
            ->assertJsonPath('coupon.campaign.name', 'Updated first booking welcome');

        $this->withHeaders($headers)->postJson("/api/admin/promotions/{$campaignId}/activate")
            ->assertOk()->assertJsonPath('campaign.status', PromotionCampaign::STATUS_ACTIVE);
        $this->withHeaders($headers)->postJson("/api/admin/coupons/{$couponId}/activate")
            ->assertOk()->assertJsonPath('coupon.status', 'active');
        $this->withHeaders($headers)->postJson("/api/admin/coupons/{$couponId}/suspend")
            ->assertOk()->assertJsonPath('coupon.status', 'suspended');
        $this->withHeaders($headers)->postJson("/api/admin/coupons/{$couponId}/activate")
            ->assertOk()->assertJsonPath('coupon.status', 'active');

        $this->actingAs($fixture['newCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'FIRST10',
        ])->assertOk()->assertJsonPath('total', 85000);
        $this->actingAs($fixture['returningCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'FIRST10',
        ])->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);

        $returningCoupon = Coupon::create([
            'organization_id' => $organization->id,
            'code' => 'RETURN10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
            'status' => Coupon::STATUS_ACTIVE,
            'eligibility_group' => Coupon::GROUP_RETURNING,
        ]);
        $this->actingAs($fixture['returningCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'RETURN10',
        ])->assertOk()->assertJsonPath('total', 90000);
        $this->actingAs($fixture['newCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'RETURN10',
        ])->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);
        Sanctum::actingAs($owner);
        $this->withHeaders($headers)->postJson("/api/admin/coupons/{$returningCoupon->id}/retire")
            ->assertOk()->assertJsonPath('coupon.status', 'retired');

        $reserved = app(DiscountService::class)->reserve($fixture['newCustomer']->id, 100000, 'FIRST10', $organization->id);
        $this->assertSame(85000, $reserved['total']);
        $this->assertSame(1, (int) $created->json('coupon.usage_limit'));
        $this->assertSame(1, (int) Coupon::findOrFail($couponId)->usage_count);
        $this->assertSame(1, (int) PromotionCampaign::findOrFail($campaignId)->usage_count);

        $this->actingAs($fixture['newCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'FIRST10',
        ])->assertUnprocessable();
        app(DiscountService::class)->release($reserved['coupon']);
        $this->assertSame(0, (int) Coupon::findOrFail($couponId)->usage_count);
        $this->assertSame(0, (int) PromotionCampaign::findOrFail($campaignId)->usage_count);

        Sanctum::actingAs($owner);
        $this->withHeaders($headers)->postJson("/api/admin/promotions/{$campaignId}/suspend")
            ->assertOk()->assertJsonPath('campaign.status', PromotionCampaign::STATUS_SUSPENDED);
        $this->withHeaders($headers)->postJson("/api/admin/promotions/{$campaignId}/retire")
            ->assertOk()->assertJsonPath('campaign.status', PromotionCampaign::STATUS_RETIRED);
        $this->assertDatabaseHas('coupons', [
            'id' => $couponId,
            'status' => 'retired',
            'is_active' => false,
        ]);
        $this->withHeaders($headers)->postJson("/api/admin/promotions/{$campaignId}/activate")
            ->assertConflict();

        $this->assertDatabaseHas('audit_logs', ['action' => 'promotion.campaign.created', 'auditable_id' => $campaignId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'promotion.coupon.created', 'auditable_id' => $couponId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'promotion.campaign.updated', 'auditable_id' => $campaignId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'promotion.campaign.retired', 'auditable_id' => $campaignId]);
    }

    public function test_coupon_management_requires_an_owner_or_manager_of_the_selected_organization(): void
    {
        $fixture = $this->tenant();
        $otherOrganization = Organization::create([
            'name' => 'Other Promotion Club',
            'slug' => 'other-promotion-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $otherOwner = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherOwner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        Sanctum::actingAs($fixture['owner']);
        $this->withHeader('X-Organization-ID', (string) $otherOrganization->id)->getJson('/api/admin/coupons')
            ->assertForbidden();

        $pricingStaff = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $pricingStaff->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
            'permissions' => ['pricing' => true],
        ]);
        // BR-21: staff keep a fixed permission set, so a per-member pricing grant no longer opens promotions.
        Sanctum::actingAs($pricingStaff);
        $this->withHeader('X-Organization-ID', (string) $fixture['organization']->id)->postJson('/api/admin/coupons', [
            'code' => 'STAFF10',
            'discount_type' => 'fixed',
            'discount_value' => 10000,
        ])->assertForbidden();

        $noGrantStaff = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $noGrantStaff->id,
            'role' => OrganizationMember::ROLE_STAFF,
            'status' => 'active',
            'permissions' => [],
        ]);
        Sanctum::actingAs($noGrantStaff);
        $this->withHeader('X-Organization-ID', (string) $fixture['organization']->id)->getJson('/api/admin/coupons')
            ->assertForbidden();
    }

    public function test_campaign_can_be_created_revised_suspended_and_retired_without_removing_history(): void
    {
        $fixture = $this->tenant();
        $owner = $fixture['owner'];
        $organization = $fixture['organization'];
        $headers = ['X-Organization-ID' => (string) $organization->id];

        Sanctum::actingAs($owner);
        $created = $this->withHeaders($headers)->postJson('/api/admin/promotions', [
            'name' => 'Returning player offer',
            'description' => 'A campaign for customers with a prior successful booking.',
            'eligibility_group' => 'returning_customers',
            'starts_at' => now()->subDay()->toIso8601String(),
            'ends_at' => now()->addMonth()->toIso8601String(),
            'usage_limit' => 20,
            'min_spend' => 50000,
            'can_combine_with_membership' => false,
        ])->assertCreated()
            ->assertJsonPath('campaign.status', 'draft')
            ->assertJsonPath('campaign.version', 1);
        $campaignId = (int) $created->json('campaign.id');

        $this->withHeaders($headers)->patchJson("/api/admin/promotions/{$campaignId}", [
            'name' => 'Returning player offer v2',
            'usage_limit' => 25,
        ])->assertOk()
            ->assertJsonPath('campaign.name', 'Returning player offer v2')
            ->assertJsonPath('campaign.version', 2);
        $this->withHeaders($headers)->postJson("/api/admin/promotions/{$campaignId}/activate")
            ->assertOk()->assertJsonPath('campaign.status', 'active');
        $this->withHeaders($headers)->postJson("/api/admin/promotions/{$campaignId}/suspend")
            ->assertOk()->assertJsonPath('campaign.status', 'suspended');
        $this->withHeaders($headers)->postJson("/api/admin/promotions/{$campaignId}/retire")
            ->assertOk()->assertJsonPath('campaign.status', 'retired');

        $this->assertDatabaseHas('promotion_campaigns', [
            'id' => $campaignId,
            'name' => 'Returning player offer v2',
            'status' => 'retired',
            'version' => 5,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'promotion.campaign.updated', 'auditable_id' => $campaignId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'promotion.campaign.suspended', 'auditable_id' => $campaignId]);
    }

    public function test_expired_coupon_cannot_be_activated_even_when_its_campaign_is_live(): void
    {
        $fixture = $this->tenant();
        $owner = $fixture['owner'];
        $organization = $fixture['organization'];
        $headers = ['X-Organization-ID' => (string) $organization->id];

        Sanctum::actingAs($owner);
        $created = $this->withHeaders($headers)->postJson('/api/admin/coupons', [
            'code' => 'ENDED10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'starts_at' => now()->subDays(3)->toIso8601String(),
            'ends_at' => now()->subDay()->toIso8601String(),
            'campaign_name' => 'Expired coupon in live campaign',
            'campaign_starts_at' => now()->subDays(3)->toIso8601String(),
            'campaign_ends_at' => now()->addDays(5)->toIso8601String(),
        ])->assertCreated();

        $couponId = (int) $created->json('coupon.id');
        $campaignId = (int) $created->json('coupon.promotion_campaign_id');
        $this->withHeaders($headers)->postJson("/api/admin/promotions/{$campaignId}/activate")
            ->assertOk()->assertJsonPath('campaign.status', PromotionCampaign::STATUS_ACTIVE);
        $this->withHeaders($headers)->postJson("/api/admin/coupons/{$couponId}/activate")
            ->assertUnprocessable();
        $this->assertDatabaseHas('coupons', ['id' => $couponId, 'status' => Coupon::STATUS_DRAFT, 'is_active' => false]);
    }

    public function test_expired_campaign_cannot_be_activated_or_apply_attached_coupon(): void
    {
        $fixture = $this->tenant();
        $owner = $fixture['owner'];
        $organization = $fixture['organization'];
        $headers = ['X-Organization-ID' => (string) $organization->id];
        Sanctum::actingAs($owner);

        $campaign = $this->withHeaders($headers)->postJson('/api/admin/promotions', [
            'name' => 'Past campaign',
            'eligibility_group' => 'all_customers',
            'starts_at' => now()->subDays(5)->toIso8601String(),
            'ends_at' => now()->subDay()->toIso8601String(),
        ])->assertCreated();
        $campaignId = (int) $campaign->json('campaign.id');
        $coupon = $this->withHeaders($headers)->postJson('/api/admin/coupons', [
            'code' => 'PAST10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'promotion_campaign_id' => $campaignId,
        ])->assertCreated();

        $this->withHeaders($headers)->postJson("/api/admin/promotions/{$campaignId}/activate")
            ->assertUnprocessable();
        $this->withHeaders($headers)->postJson('/api/admin/coupons/'.$coupon->json('coupon.id').'/activate')
            ->assertConflict();
        $this->actingAs($fixture['newCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'PAST10',
        ])->assertUnprocessable();
    }

    public function test_coupon_scope_and_customer_group_reject_foreign_venues_courts_and_tenants(): void
    {
        $fixture = $this->tenant();
        $organization = $fixture['organization'];
        $venue = $fixture['venue'];
        $court = $fixture['court'];
        $otherCourt = Court::create(['venue_id' => $venue->id, 'name' => 'Other Promotion Court', 'is_active' => true]);
        $headers = ['X-Organization-ID' => (string) $organization->id];

        Sanctum::actingAs($fixture['owner']);
        $created = $this->withHeaders($headers)->postJson('/api/admin/coupons', [
            'code' => 'COURT10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'eligibility_group' => Coupon::GROUP_FIRST_TIME,
            'venue_id' => $venue->id,
            'court_id' => $court->id,
            'campaign_venue_id' => $venue->id,
            'campaign_court_id' => $court->id,
        ])->assertCreated()
            ->assertJsonPath('coupon.venue_id', $venue->id)
            ->assertJsonPath('coupon.court_id', $court->id)
            ->assertJsonPath('coupon.campaign.venue_id', $venue->id)
            ->assertJsonPath('coupon.campaign.court_id', $court->id);

        $couponId = (int) $created->json('coupon.id');
        $campaignId = (int) $created->json('coupon.promotion_campaign_id');
        $this->withHeaders($headers)->postJson("/api/admin/promotions/{$campaignId}/activate")->assertOk();
        $this->withHeaders($headers)->postJson("/api/admin/coupons/{$couponId}/activate")->assertOk();

        $this->actingAs($fixture['newCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'COURT10',
            'venue_id' => $venue->id,
            'court_id' => $court->id,
        ])->assertOk()->assertJsonPath('total', 90000);

        $this->actingAs($fixture['newCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'COURT10',
            'venue_id' => $venue->id,
            'court_id' => $otherCourt->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);

        $this->actingAs($fixture['newCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'COURT10',
        ])->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);

        $this->actingAs($fixture['returningCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'COURT10',
            'venue_id' => $venue->id,
            'court_id' => $court->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);

        $customerWithOtherCourtBooking = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $customerWithOtherCourtBooking->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);
        Reservation::create([
            'organization_id' => $organization->id,
            'user_id' => $customerWithOtherCourtBooking->id,
            'court_id' => $otherCourt->id,
            'code' => 'RSV-OTHER-'.Str::upper(Str::random(8)),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 100000,
            'payment_model' => 'legacy_wallet_v1',
        ]);
        $this->actingAs($customerWithOtherCourtBooking)->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'COURT10',
            'venue_id' => $venue->id,
            'court_id' => $court->id,
        ])->assertOk()->assertJsonPath('total', 90000);

        $otherOrganization = Organization::create([
            'name' => 'Foreign Promotion Club',
            'slug' => 'foreign-promotion-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $fixture['newCustomer']->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);
        $foreignVenue = Venue::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Foreign Venue',
            'slug' => 'foreign-venue-'.Str::lower(Str::random(8)),
            'status' => 'published',
            'is_active' => true,
        ]);
        $foreignCourt = Court::create(['venue_id' => $foreignVenue->id, 'name' => 'Foreign Court', 'is_active' => true]);
        $this->actingAs($fixture['newCustomer'])->withHeader('X-Organization-ID', (string) $otherOrganization->id)
            ->postJson('/api/coupons/preview', [
                'subtotal' => 100000,
                'code' => 'COURT10',
                'venue_id' => $foreignVenue->id,
                'court_id' => $foreignCourt->id,
            ])->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);

        $unscopedLegacyCoupon = Coupon::create([
            'code' => 'LEGACYGLOBAL',
            'discount_type' => 'fixed',
            'discount_value' => 10000,
            'is_active' => true,
        ]);
        $this->actingAs($fixture['newCustomer'])->withHeaders($headers)->postJson('/api/coupons/preview', [
            'subtotal' => 100000,
            'code' => 'LEGACYGLOBAL',
        ])->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);
        $this->assertSame(0, (int) $unscopedLegacyCoupon->fresh()->usage_count);
        $this->assertSame(0, (int) Coupon::findOrFail($couponId)->usage_count);
    }

    public function test_non_combinable_coupon_rejects_booking_preview_and_rolls_back_reserved_use(): void
    {
        $fixture = $this->tenant();
        $coupon = Coupon::create([
            'organization_id' => $fixture['organization']->id,
            'code' => 'NOCOMBINE10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
            'status' => Coupon::STATUS_ACTIVE,
            'can_combine_with_membership' => false,
        ]);
        $plan = TenantMembershipPlan::create([
            'organization_id' => $fixture['organization']->id,
            'name' => 'No Combine '.Str::random(8),
            'version' => 1,
            'price' => 100000,
            'benefits' => ['discount_percent' => 10],
            'status' => 'published',
            'published_at' => now(),
        ]);
        $membership = TenantMembership::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $fixture['newCustomer']->id,
            'plan_id' => $plan->id,
            'plan_version' => $plan->version,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
        MembershipEntitlement::create([
            'organization_id' => $fixture['organization']->id,
            'membership_id' => $membership->id,
            'benefit_code' => 'discount_percent',
            'benefit_value' => ['value' => 10, 'plan_version' => $plan->version],
            'starts_at' => $membership->starts_at,
            'ends_at' => $membership->ends_at,
        ]);

        try {
            app(DiscountService::class)->reserve(
                $fixture['newCustomer']->id,
                100000,
                $coupon->code,
                $fixture['organization']->id,
                $fixture['venue']->id,
                $fixture['court']->id,
            );
            $this->fail('A coupon that forbids membership stacking must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('coupon_code', $exception->errors());
            $this->assertSame(0, (int) $coupon->fresh()->usage_count);
        }

        $this->actingAs($fixture['newCustomer'])->withHeader('X-Organization-ID', (string) $fixture['organization']->id)
            ->postJson('/api/coupons/preview', [
                'subtotal' => 100000,
                'code' => $coupon->code,
                'venue_id' => $fixture['venue']->id,
                'court_id' => $fixture['court']->id,
            ])->assertUnprocessable()->assertJsonValidationErrors(['coupon_code']);
        $this->assertSame(0, (int) $coupon->fresh()->usage_count);
    }

    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Promotion Club',
            'slug' => 'promotion-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $plan = PlatformPlan::create([
            'code' => 'PROMO-'.Str::upper(Str::random(6)),
            'name' => 'Promotion Plan',
            'price' => 100000,
            'billing_cycle' => 'monthly',
            'is_active' => true,
        ]);
        PlatformSubscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $newCustomer = User::factory()->create(['email_verified_at' => now()]);
        $returningCustomer = User::factory()->create(['email_verified_at' => now()]);
        foreach ([$owner, $newCustomer, $returningCustomer] as $index => $user) {
            OrganizationMember::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role' => $index === 0 ? OrganizationMember::ROLE_ORGANIZATION_OWNER : OrganizationMember::ROLE_CUSTOMER,
                'status' => 'active',
            ]);
        }
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Promotion Venue',
            'slug' => 'promotion-venue-'.Str::lower(Str::random(8)),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Promotion Court', 'is_active' => true]);
        Reservation::create([
            'organization_id' => $organization->id,
            'user_id' => $returningCustomer->id,
            'court_id' => $court->id,
            'code' => 'RSV-PROMO-'.Str::upper(Str::random(8)),
            'status' => 'confirmed',
            'currency' => 'VND',
            'total_amount' => 100000,
            'payment_model' => 'legacy_wallet_v1',
        ]);

        return compact('organization', 'owner', 'newCustomer', 'returningCustomer', 'venue', 'court');
    }
}
