<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\PriceRule;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VenueSportPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_court_rule_beats_venue_sport_rule_beats_venue_rule_beats_organization_rule(): void
    {
        [$organization, $user, $venue] = $this->tenant();
        $court = Court::create(['venue_id' => $venue->id, 'sport_type' => 'badminton', 'name' => 'Court 1', 'is_active' => true]);
        $rule = fn (int $price, array $scope): PriceRule => PriceRule::create($scope + ['organization_id' => $organization->id, 'type' => 'base', 'price' => $price, 'version' => 1]);
        $rule(100000, []);
        $venueRule = $rule(120000, ['venue_id' => $venue->id]);
        $sportRule = $rule(140000, ['venue_id' => $venue->id, 'sport_type' => 'badminton']);
        $rule(999000, ['venue_id' => $venue->id, 'sport_type' => 'tennis']);
        $courtRule = $rule(160000, ['court_id' => $court->id]);
        $slot = now(config('app.timezone'))->addDays(3)->setTime(18, 0)->toIso8601String();

        foreach ([[160000, $courtRule], [140000, $sportRule], [120000, $venueRule], [100000, null]] as $i => [$expected, $next]) {
            $this->actingAs($user)->withHeaders(['X-Organization-ID' => (string) $organization->id, 'Idempotency-Key' => "rank-{$i}"])
                ->postJson('/api/price-quotes', ['court_id' => $court->id, 'slots' => [$slot]])
                ->assertCreated()->assertJsonPath('quote.total_amount', $expected);
            $next?->update(['is_active' => false]);
        }
    }

    public function test_sport_rule_must_name_its_venue_on_create_and_update(): void
    {
        [$organization, $user, $venue] = $this->tenant(OrganizationMember::ROLE_ORGANIZATION_OWNER);
        $this->subscribe($organization);
        $headers = fn (string $key): array => ['X-Organization-ID' => (string) $organization->id, 'Idempotency-Key' => $key];

        $this->actingAs($user)->withHeaders($headers('sport-no-venue'))
            ->postJson('/api/price-rules', ['type' => 'base', 'price' => 100000, 'sport_type' => 'tennis'])
            ->assertUnprocessable()->assertJsonValidationErrors(['venue_id']);
        $this->actingAs($user)->withHeaders($headers('sport-bad'))
            ->postJson('/api/price-rules', ['type' => 'base', 'price' => 100000, 'venue_id' => $venue->id, 'sport_type' => 'squash'])
            ->assertUnprocessable()->assertJsonValidationErrors(['sport_type']);
        $this->assertSame(0, PriceRule::count());

        $id = $this->actingAs($user)->withHeaders($headers('sport-ok'))
            ->postJson('/api/price-rules', ['type' => 'base', 'price' => 100000, 'venue_id' => $venue->id, 'sport_type' => 'tennis'])
            ->assertCreated()->assertJsonPath('rule.sport_type', 'tennis')->json('rule.id');
        $this->actingAs($user)->withHeaders($headers('sport-drop-venue'))
            ->patchJson("/api/price-rules/{$id}", ['venue_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['venue_id']);
        $this->assertSame($venue->id, PriceRule::findOrFail($id)->venue_id);

        $orgRule = PriceRule::create(['organization_id' => $organization->id, 'type' => 'base', 'price' => 90000, 'version' => 1]);
        $this->actingAs($user)->withHeaders($headers('org-add-sport'))
            ->patchJson("/api/price-rules/{$orgRule->id}", ['sport_type' => 'tennis'])
            ->assertUnprocessable()->assertJsonValidationErrors(['venue_id']);
    }

    /** @return array{0: Organization, 1: User, 2: Venue} */
    private function tenant(string $role = OrganizationMember::ROLE_CUSTOMER): array
    {
        $organization = Organization::create(['name' => 'Sport Org', 'slug' => 'sport-org-'.uniqid(), 'status' => 'active', 'partner_status' => 'approved']);
        $user = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Sport Venue', 'slug' => 'sport-venue-'.uniqid(), 'status' => 'published', 'is_active' => true]);

        return [$organization, $user, $venue];
    }

    private function subscribe(Organization $organization): void
    {
        $plan = PlatformPlan::create(['code' => 'PLAN-'.Str::upper(Str::random(6)), 'name' => 'Pricing Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create(['organization_id' => $organization->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subMonth(), 'ends_at' => now()->addMonth()]);
    }
}
