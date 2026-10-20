<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PlatformPlan;
use App\Models\PlatformSubscription;
use App\Models\PriceRule;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;
use App\Services\Reservation\PriceQuoteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PricingTenantAndBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_reads_stay_with_the_court_owner(): void
    {
        ['organization' => $mine, 'owner' => $owner, 'court' => $court] = $this->tenant();
        PriceRule::create(['organization_id' => $mine->id, 'venue_id' => $court->venue_id, 'court_id' => $court->id, 'type' => 'base', 'price' => 100000, 'version' => 1]);
        $other = $this->tenant();
        $staff = $this->member($other['organization'], OrganizationMember::ROLE_STAFF);
        $customer = $this->member($other['organization'], OrganizationMember::ROLE_CUSTOMER);

        foreach (['/api/price-rules', '/api/pricing/calendar'] as $path) {
            foreach ([$other['owner'], $staff, $customer] as $outsider) {
                $this->actingAs($outsider)->getJson("{$path}?court_id={$court->id}")->assertForbidden();
            }
            $this->actingAs($owner)->getJson("{$path}?court_id={$court->id}")->assertOk()->assertJsonCount(1, 'rules');
        }
    }

    public function test_price_rule_location_must_belong_to_the_acting_organization(): void
    {
        ['organization' => $mine, 'owner' => $owner, 'venue' => $venue] = $this->tenant();
        $this->subscribe($mine);
        $sibling = Venue::create(['organization_id' => $mine->id, 'name' => 'Sibling', 'slug' => 'sibling-'.Str::lower(Str::random(8)), 'status' => 'published', 'is_active' => true]);
        $siblingCourt = Court::create(['venue_id' => $sibling->id, 'name' => 'Sibling Court', 'is_active' => true]);
        $other = $this->tenant();
        $headers = fn (string $key): array => ['X-Organization-ID' => (string) $mine->id, 'Idempotency-Key' => $key];

        $this->actingAs($owner)->withHeaders($headers('foreign-venue'))
            ->postJson('/api/price-rules', ['type' => 'base', 'price' => 100000, 'venue_id' => $other['venue']->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['venue_id']);
        $this->actingAs($owner)->withHeaders($headers('foreign-court'))
            ->postJson('/api/price-rules', ['type' => 'base', 'price' => 100000, 'court_id' => $other['court']->id])
            ->assertForbidden();
        $this->actingAs($owner)->withHeaders($headers('court-outside-venue'))
            ->postJson('/api/price-rules', ['type' => 'base', 'price' => 100000, 'venue_id' => $venue->id, 'court_id' => $siblingCourt->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['court_id']);
        $this->assertSame(0, PriceRule::count());

        $rule = PriceRule::create(['organization_id' => $mine->id, 'venue_id' => $venue->id, 'type' => 'base', 'price' => 100000, 'version' => 1]);
        $this->actingAs($owner)->withHeaders($headers('move-to-foreign-venue'))
            ->patchJson("/api/price-rules/{$rule->id}", ['venue_id' => $other['venue']->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['venue_id']);
        $this->assertSame($venue->id, $rule->fresh()->venue_id);
    }

    /** @return array<string, array{0:string, 1:string, 2:int|string}> */
    public static function boundaryCases(): array
    {
        return [
            'base before the peak window' => ['17:00', '23:59', 100000],
            'slot across the 18:00 price change' => ['17:30', '23:59', 'mốc đổi giá'],
            'peak window' => ['18:00', '23:59', 200000],
            'last slot of a day open until 23:59' => ['23:00', '23:59', 200000],
            'after closing time' => ['23:00', '22:00', 'ngoài lịch hoạt động'],
            'slot across midnight' => ['23:30', '23:59', 'ngoài lịch hoạt động'],
        ];
    }

    #[DataProvider('boundaryCases')]
    public function test_slot_price_respects_price_changes_and_operating_hours(string $time, string $closesAt, int|string $expected): void
    {
        ['organization' => $organization, 'venue' => $venue, 'court' => $court] = $this->tenant();
        $this->openDaily($venue, $closesAt);
        PriceRule::create(['organization_id' => $organization->id, 'venue_id' => $venue->id, 'type' => 'base', 'price' => 100000, 'version' => 1]);
        PriceRule::create(['organization_id' => $organization->id, 'venue_id' => $venue->id, 'type' => 'peak', 'starts_at' => '18:00', 'ends_at' => '23:59', 'price' => 200000, 'version' => 1]);

        $this->assertSubtotal($court, $this->slotAt($time), $expected);
    }

    public function test_court_rule_beats_legacy_hourly_price_which_beats_venue_rule(): void
    {
        ['organization' => $organization, 'venue' => $venue, 'court' => $court] = $this->tenant();
        $slot = $this->slotAt('08:00');
        $weekday = Carbon::parse($slot)->dayOfWeekIso;
        $weekly = ['organization_id' => $organization->id, 'type' => 'weekly', 'weekday' => $weekday, 'starts_at' => '08:00', 'ends_at' => '09:00', 'version' => 1];
        PriceRule::create($weekly + ['venue_id' => $venue->id, 'price' => 150000]);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $weekday, 'starts_at' => '08:00', 'ends_at' => '09:00', 'price' => 120000, 'is_active' => true]);
        $this->assertSubtotal($court, $slot, 120000);

        PriceRule::create($weekly + ['venue_id' => $venue->id, 'court_id' => $court->id, 'price' => 200000]);
        $this->assertSubtotal($court, $slot, 200000);
    }

    public function test_legacy_prices_only_slots_of_their_own_length(): void
    {
        ['organization' => $organization, 'venue' => $venue, 'court' => $court] = $this->tenant();
        $court->update(['slot_duration' => 90]);
        $slot = $this->slotAt('08:00');
        $weekday = Carbon::parse($slot)->dayOfWeekIso;
        foreach (['08:00' => '09:00', '09:00' => '10:00'] as $from => $to) {
            CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $weekday, 'starts_at' => $from, 'ends_at' => $to, 'price' => 300000, 'is_active' => true]);
        }
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $weekday, 'starts_at' => '10:00', 'ends_at' => '11:30', 'price' => 250000, 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'venue_id' => $venue->id, 'type' => 'base', 'price' => 100000, 'version' => 1]);

        $this->assertSubtotal($court, $slot, 100000);
        $this->assertSubtotal($court, $this->slotAt('10:00'), 250000);
    }

    private function assertSubtotal(Court $court, string $slot, int|string $expected): void
    {
        try {
            $subtotal = app(PriceQuoteService::class)->calculateSubtotal($court->fresh(), [$slot]);
        } catch (ValidationException $exception) {
            $this->assertIsString($expected, 'Unexpected refusal: '.$exception->getMessage());
            $this->assertStringContainsString($expected, $exception->errors()['slots'][0]);

            return;
        }
        $this->assertSame($expected, $subtotal);
    }

    private function slotAt(string $time): string
    {
        [$hour, $minute] = explode(':', $time);

        return Carbon::now('Asia/Ho_Chi_Minh')->addDays(3)->setTime((int) $hour, (int) $minute)->toIso8601String();
    }

    private function openDaily(Venue $venue, string $closesAt): void
    {
        $hours = [];
        for ($day = 1; $day <= 7; $day++) {
            $hours[(string) $day] = ['closed' => false, 'intervals' => [['start' => '06:00', 'end' => $closesAt]]];
        }
        VenueOperatingSchedule::create(['venue_id' => $venue->id, 'scope_key' => 'venue', 'timezone' => 'Asia/Ho_Chi_Minh', 'version' => 1, 'weekly_hours' => $hours]);
    }

    /** @return array{organization: Organization, owner: User, venue: Venue, court: Court} */
    private function tenant(): array
    {
        $organization = Organization::create(['name' => 'Pricing Org', 'slug' => 'pricing-org-'.Str::lower(Str::random(8)), 'status' => 'active', 'partner_status' => 'approved']);
        $owner = $this->member($organization, OrganizationMember::ROLE_ORGANIZATION_OWNER);
        $venue = Venue::create(['organization_id' => $organization->id, 'name' => 'Pricing Venue', 'slug' => 'pricing-venue-'.Str::lower(Str::random(8)), 'status' => 'published', 'is_active' => true]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Court 1', 'is_active' => true]);

        return compact('organization', 'owner', 'venue', 'court');
    }

    private function member(Organization $organization, string $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active']);

        return $user;
    }

    private function subscribe(Organization $organization): void
    {
        $plan = PlatformPlan::create(['code' => 'PLAN-'.Str::upper(Str::random(6)), 'name' => 'Pricing Plan', 'price' => 100000, 'billing_cycle' => 'monthly', 'is_active' => true]);
        PlatformSubscription::create(['organization_id' => $organization->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subMonth(), 'ends_at' => now()->addMonth()]);
    }
}
