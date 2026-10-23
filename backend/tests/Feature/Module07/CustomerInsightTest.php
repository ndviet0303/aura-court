<?php

namespace Tests\Feature\Module07;

use App\Domain\Customer\Rules\CustomerTier;
use App\Models\BookingAttendance;
use App\Models\Court;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Module07\Concerns\ServesCustomers;
use Tests\TestCase;

/** FR-110, BR-140–BR-142 (UC-7.1, UC-7.2): customers of a venue with their quarter figures, tier and usage rate. */
class CustomerInsightTest extends TestCase
{
    use RefreshDatabase;
    use ServesCustomers;

    private Court $court;

    private User $owner;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo($this->at('2026-12-01 08:00'));
        $this->court = $this->bookableCourt();
        $this->owner = $this->ownerOf($this->court->venue->organization);
        $this->customer = User::factory()->create(['name' => 'Nguyễn Văn Khách', 'phone' => '0912345678']);
    }

    public function test_br_141_tier_thresholds_are_15_35_60_completed_bookings(): void
    {
        $tiers = array_map(fn (int $n) => CustomerTier::for($n), [0, 14, 15, 34, 35, 59, 60]);

        $this->assertSame(['bronze', 'bronze', 'silver', 'silver', 'gold', 'gold', 'diamond'], $tiers);
    }

    public function test_fr_110_br_141_br_142_quarter_counts_by_completion_time_in_venue_time_at_this_venue_only(): void
    {
        $this->visit($this->court, $this->customer, $this->at('2026-12-31 23:30'));
        $this->visit($this->court, $this->customer, $this->at('2027-01-01 00:30'));
        $this->visit($this->court, $this->customer, $this->at('2026-11-03 10:00'), BookingAttendance::STATUS_NO_SHOW);
        $this->booking($this->court, $this->at('2026-12-05 10:00'))->update(['user_id' => $this->customer->id]);
        $sameOrganization = $this->activeCourt($this->openVenue(['organization_id' => $this->court->venue->organization_id]));
        $this->visit($sameOrganization, $this->customer, $this->at('2026-12-10 10:00'));
        $venue = $this->court->venue;

        $this->actingAs($this->owner)
            ->getJson("/api/operations/venues/{$venue->id}/customers?quarter=2026-Q4&search=0912345678", $this->tenant($venue))
            ->assertOk()
            ->assertJsonPath('quarter', '2026-Q4')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Nguyễn Văn Khách')
            ->assertJsonPath('data.0.completed', 1)
            ->assertJsonPath('data.0.no_show', 1)
            ->assertJsonPath('data.0.used_minutes', 60)
            ->assertJsonPath('data.0.completed_amount', 100000)
            ->assertJsonPath('data.0.usage_rate', 50)
            ->assertJsonPath('data.0.tier', 'bronze')
            ->assertJsonPath('data.0.last_used_at', $this->at('2027-01-01 00:30')->toIso8601String());

        $this->getJson("/api/operations/venues/{$venue->id}/customers?quarter=2027-Q1", $this->tenant($venue))
            ->assertJsonPath('data.0.completed', 1)
            ->assertJsonPath('data.0.no_show', 0)
            ->assertJsonPath('data.0.usage_rate', 100);

        $this->getJson("/api/operations/venues/{$venue->id}/customers?quarter=2026-13", $this->tenant($venue))->assertUnprocessable();
    }

    public function test_br_142_a_customer_without_completed_or_missed_bookings_has_no_usage_rate(): void
    {
        $this->booking($this->court, $this->at('2026-12-05 10:00'))->update(['user_id' => $this->customer->id]);
        $venue = $this->court->venue;

        $this->actingAs($this->owner)->getJson("/api/operations/venues/{$venue->id}/customers", $this->tenant($venue))
            ->assertOk()
            ->assertJsonPath('quarter', '2026-Q4')
            ->assertJsonPath('data.0.completed', 0)
            ->assertJsonPath('data.0.usage_rate', null);
    }

    public function test_br_141_fifteen_completed_bookings_in_the_quarter_rank_silver_and_the_next_quarter_starts_over(): void
    {
        foreach (range(1, 15) as $day) {
            $this->visit($this->court, $this->customer, $this->at(sprintf('2026-11-%02d 10:00', $day)));
        }
        $venue = $this->court->venue;

        $this->actingAs($this->owner)->getJson("/api/operations/venues/{$venue->id}/customers/{$this->customer->id}", $this->tenant($venue))
            ->assertOk()
            ->assertJsonPath('customer.completed', 15)
            ->assertJsonPath('customer.tier', 'silver')
            ->assertJsonCount(15, 'history')
            ->assertJsonPath('history.0.operational_status', 'completed');

        $this->travelTo($this->at('2027-01-02 08:00'));
        $this->getJson("/api/operations/venues/{$venue->id}/customers/{$this->customer->id}", $this->tenant($venue))
            ->assertJsonPath('quarter', '2027-Q1')
            ->assertJsonPath('customer.tier', 'bronze');
        $this->getJson("/api/operations/venues/{$venue->id}/customers/{$this->customer->id}?quarter=2026-Q4", $this->tenant($venue))
            ->assertJsonPath('customer.tier', 'silver');
    }

    public function test_br_140_staff_see_customers_of_their_assigned_venue_only(): void
    {
        $venue = $this->court->venue;
        $other = $this->openVenue(['organization_id' => $venue->organization_id]);
        $staff = $this->staffOf($venue);
        $this->visit($this->court, $this->customer, $this->at('2026-11-03 10:00'));

        $this->actingAs($staff)->getJson("/api/operations/venues/{$venue->id}/customers", $this->tenant($venue))
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/operations/venues/{$other->id}/customers", $this->tenant($venue))
            ->assertForbidden()->assertJsonPath('code', 'VENUE_NOT_ASSIGNED');

        $outsider = $this->ownerOf($this->openVenue()->organization);
        $this->actingAs($outsider)->getJson("/api/operations/venues/{$venue->id}/customers", $this->tenant($venue))->assertForbidden();
        $this->actingAs($this->customer)->getJson("/api/operations/venues/{$venue->id}/customers", $this->tenant($venue))->assertForbidden();
    }

    public function test_fr_110_a_user_who_never_booked_at_the_venue_is_not_its_customer(): void
    {
        $venue = $this->court->venue;

        $this->actingAs($this->owner)->getJson("/api/operations/venues/{$venue->id}/customers/{$this->customer->id}", $this->tenant($venue))
            ->assertNotFound()->assertJsonPath('code', 'CUSTOMER_NOT_FOUND');
    }
}
