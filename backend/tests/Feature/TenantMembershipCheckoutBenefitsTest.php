<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosProduct;
use App\Models\PriceRule;
use App\Models\TenantMembership;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantMembershipCheckoutBenefitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_membership_plan_is_reloaded_and_applies_only_to_its_tenant_in_reservations_and_pos(): void
    {
        config([
            'features.flags.direct_owner_payments' => false,
            'features.flags.tenant_pos_and_wallet' => true,
            'features.pilot_organizations' => [],
        ]);

        $tenant = $this->tenant('Primary membership club');
        $otherTenant = $this->tenant('Other membership club');
        config(['features.pilot_organizations' => [
            (string) $tenant['organization']->id,
            (string) $otherTenant['organization']->id,
        ]]);
        OrganizationMember::create([
            'organization_id' => $otherTenant['organization']->id,
            'user_id' => $tenant['customer']->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        PriceRule::create(['organization_id' => $tenant['organization']->id, 'type' => 'base', 'price' => 100000]);
        PriceRule::create(['organization_id' => $otherTenant['organization']->id, 'type' => 'base', 'price' => 100000]);
        $primaryProduct = PosProduct::create([
            'organization_id' => $tenant['organization']->id,
            'sku' => 'MEMBER-PRIMARY',
            'name' => 'Primary item',
            'price' => 100000,
            'cost' => 50000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
        $otherProduct = PosProduct::create([
            'organization_id' => $otherTenant['organization']->id,
            'sku' => 'MEMBER-OTHER',
            'name' => 'Other item',
            'price' => 100000,
            'cost' => 50000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $headers = ['X-Organization-ID' => (string) $tenant['organization']->id];
        $createdPlan = $this->actingAs($tenant['owner'])->withHeaders($headers)
            ->postJson('/api/admin/memberships/plans', [
                'name' => 'Court Gold',
                'price' => 250000,
                'billingCycle' => 'monthly',
                'courtDiscountPercent' => 10,
                'pointMultiplier' => 1.5,
                'advanceBookingDays' => 10,
                'freeWeekendHours' => 0,
                'perks' => ['Court discount'],
            ]);
        if ($createdPlan->status() !== 201) {
            throw new \RuntimeException($createdPlan->getContent());
        }
        $planId = (int) $createdPlan->json('id');

        $this->actingAs($tenant['owner'])->withHeaders($headers)->getJson('/api/admin/memberships/plans')
            ->assertOk()
            ->assertJsonPath('0.id', $planId)
            ->assertJsonPath('0.courtDiscountPercent', 10);

        $this->actingAs($tenant['owner'])->withHeaders($headers + ['Idempotency-Key' => 'assign-foreign-tier'])
            ->postJson('/api/memberships', [
                'user_id' => $otherTenant['customer']->id,
                'plan_id' => $planId,
                'months' => 1,
            ])->assertUnprocessable();
        $this->assertDatabaseMissing('tenant_memberships', [
            'organization_id' => $tenant['organization']->id,
            'user_id' => $otherTenant['customer']->id,
            'plan_id' => $planId,
        ]);

        $this->actingAs($tenant['owner'])->withHeaders($headers + ['Idempotency-Key' => 'assign-primary-tier'])
            ->postJson('/api/memberships', [
                'user_id' => $tenant['customer']->id,
                'plan_id' => $planId,
                'months' => 1,
            ])->assertCreated()->assertJsonPath('membership.plan_version', 1);

        $membership = TenantMembership::query()
            ->where('organization_id', $tenant['organization']->id)
            ->where('user_id', $tenant['customer']->id)
            ->with('entitlements')
            ->firstOrFail();
        $this->assertDatabaseHas('membership_entitlements', [
            'organization_id' => $tenant['organization']->id,
            'membership_id' => $membership->id,
            'benefit_code' => 'discount_percent',
        ]);

        $this->actingAs($tenant['customer'])->withHeaders($headers)->getJson('/api/membership')
            ->assertOk()
            ->assertJsonPath('membership.organization_id', $tenant['organization']->id)
            ->assertJsonPath('membership.plan.discount_percent', 10);

        $v2Time = now()->addDays(6)->setTime(11, 0)->startOfHour();
        $quote = $this->actingAs($tenant['customer'])->withHeaders($headers + ['Idempotency-Key' => 'member-v2-quote'])
            ->postJson('/api/price-quotes', [
                'court_id' => $tenant['court']->id,
                'slots' => [$v2Time->toIso8601String()],
            ])->assertCreated()
            ->assertJsonPath('quote.subtotal', 100000)
            ->assertJsonPath('quote.total_amount', 90000)
            ->assertJsonPath('quote.benefit_snapshot.membership_discount_amount', 10000);

        $this->actingAs($tenant['customer'])->withHeaders($headers + ['Idempotency-Key' => 'member-v2-hold'])
            ->postJson('/api/reservations', ['quote_token' => $quote->json('quote.quote_token'), 'expected_total' => $quote->json('quote.total_amount')])
            ->assertCreated()
            ->assertJsonPath('reservation.total_amount', 90000)
            ->assertJsonPath('reservation.metadata.benefit_snapshot.membership_discount_amount', 10000);

        $this->actingAs($tenant['owner'])->withHeaders($headers)->postJson('/api/pos/quote', [
            'customer_id' => $tenant['customer']->id,
            'items' => [['product_id' => $primaryProduct->id, 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('quote.discount_amount', 10000)
            ->assertJsonPath('quote.membership_discount_amount', 10000)
            ->assertJsonPath('quote.membership_snapshot.plan_version', 1);

        $otherHeaders = ['X-Organization-ID' => (string) $otherTenant['organization']->id];
        $otherTime = now()->addDays(7)->setTime(12, 0)->startOfHour();
        $this->actingAs($tenant['customer'])->withHeaders($otherHeaders + ['Idempotency-Key' => 'member-other-v2-quote'])
            ->postJson('/api/price-quotes', [
                'court_id' => $otherTenant['court']->id,
                'slots' => [$otherTime->toIso8601String()],
            ])->assertCreated()
            ->assertJsonPath('quote.subtotal', 100000)
            ->assertJsonPath('quote.total_amount', 100000)
            ->assertJsonPath('quote.benefit_snapshot', null);

        $this->actingAs($otherTenant['owner'])->withHeaders($otherHeaders)->postJson('/api/pos/quote', [
            'customer_id' => $tenant['customer']->id,
            'items' => [['product_id' => $otherProduct->id, 'quantity' => 1]],
        ])->assertOk()
            ->assertJsonPath('quote.discount_amount', 0)
            ->assertJsonPath('quote.membership_snapshot', null);
    }

    private function tenant(string $name): array
    {
        $organization = Organization::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $customer = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $customer->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => $name.' venue',
            'slug' => Str::slug($name).'-venue-'.Str::lower(Str::random(8)),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => $name.' court', 'is_active' => true]);

        return compact('organization', 'owner', 'customer', 'venue', 'court');
    }
}
