<?php

namespace Tests\Feature\ResaleVenueCredit;

use App\Models\User;
use App\Models\Venue;
use App\Services\Finance\VenueCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BooksCourts;
use Tests\Feature\PosVenueInventory\Concerns\SellsAtVenues;
use Tests\TestCase;

/** venue-credit "Venue credit visibility": a customer sees their own credit, an owner and a cashier only their organization's. */
class CreditVisibilityTest extends TestCase
{
    use BooksCourts;
    use RefreshDatabase;
    use SellsAtVenues;

    private Venue $venueA;

    private Venue $venueB;

    private User $customer;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePos();
        $this->enableDirectPayments();
        $this->venueA = $this->openVenue();
        $this->venueB = $this->openVenue();
        $this->customer = $this->customer();
        $this->other = $this->customer();
        $this->grant($this->venueA, $this->customer, 50000);
        $this->grant($this->venueB, $this->customer, 20000);
        $this->grant($this->venueA, $this->other, 70000);
    }

    public function test_a_customer_sees_their_balance_and_entries_at_each_organization(): void
    {
        $data = collect($this->actingAs($this->customer)->getJson('/api/me/venue-credits')->assertOk()->assertJsonCount(2, 'data')->json('data'));

        $this->assertEqualsCanonicalizing(
            [$this->venueA->organization_id => 50000, $this->venueB->organization_id => 20000],
            $data->mapWithKeys(fn (array $row): array => [$row['organization']['id'] => $row['balance']])->all(),
        );
        $entry = $data->firstWhere('organization.id', $this->venueA->organization_id)['entries'][0];
        $this->assertSame(['issue', 50000, 'entitlement_transfer'], [$entry['type'], $entry['amount'], $entry['source_type']]);
        $this->assertArrayNotHasKey('customer', $entry);
    }

    public function test_the_owner_sees_their_organization_outstanding_credit_only(): void
    {
        $owner = $this->ownerOf($this->venueA->organization);

        $this->pos($owner, 'GET', 'owner-venue-credits', $this->venueA)->assertOk()
            ->assertJsonPath('outstanding', 120000)->assertJsonCount(2, 'data')->assertJsonPath('data.0.customer.name', $this->other->name);
        $this->pos($this->ownerOf($this->venueB->organization), 'GET', 'owner-venue-credits', $this->venueA)->assertForbidden();
    }

    public function test_a_cashier_sees_the_served_customer_balance_at_their_organization(): void
    {
        $this->pos($this->staffOf($this->venueA), 'GET', "pos/venue-credits/customers/{$this->customer->id}", $this->venueA)->assertOk()->assertJsonPath('balance', 50000);
        $this->pos($this->staffOf($this->venueB), 'GET', "pos/venue-credits/customers/{$this->customer->id}", $this->venueB)->assertOk()->assertJsonPath('balance', 20000);
        $this->pos($this->staffOf($this->venueB), 'GET', "pos/venue-credits/customers/{$this->customer->id}", $this->venueA)->assertForbidden();
        $this->actingAs($this->customer)->getJson("/api/pos/venue-credits/customers/{$this->other->id}", ['X-Organization-ID' => (string) $this->venueA->organization_id])->assertForbidden();
    }

    private function grant(Venue $venue, User $user, int $amount): void
    {
        app(VenueCreditService::class)->issue($venue->organization_id, $user->id, $amount, 'entitlement_transfer', 'test-'.Str::uuid());
    }
}
