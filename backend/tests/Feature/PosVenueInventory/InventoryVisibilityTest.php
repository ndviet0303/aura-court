<?php

namespace Tests\Feature\PosVenueInventory;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\PosVenueInventory\Concerns\SellsAtVenues;
use Tests\TestCase;

/** pos-venue-inventory: stock, movements and indicators are read for one venue within the caller's scope. */
class InventoryVisibilityTest extends TestCase
{
    use RefreshDatabase;
    use SellsAtVenues;

    private Venue $venueA;

    private Venue $venueB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePos();
        $this->venueA = $this->openVenue();
        $this->venueB = $this->openVenue(['organization_id' => $this->venueA->organization_id]);
    }

    public function test_staff_read_their_venue_and_only_its_figures(): void
    {
        $owner = $this->ownerOf($this->venueA->organization);
        $product = $this->product($this->venueA, ['min_stock' => 5]);
        foreach ([$this->venueA->id => 8, $this->venueB->id => 30] as $venueId => $quantity) {
            $this->pos($owner, 'POST', "pos/products/{$product->id}/stock", $this->venueA, ['venue_id' => $venueId, 'type' => 'in', 'quantity' => $quantity])->assertOk();
        }
        $staff = $this->staffOf($this->venueA);
        $at = "venue_id={$this->venueA->id}";

        $this->pos($staff, 'GET', "pos/products?{$at}", $this->venueA)->assertOk()->assertJsonPath('0.stock_quantity', 8);
        $this->pos($staff, 'GET', "pos/inventory/movements?{$at}", $this->venueA)->assertOk()
            ->assertJsonCount(1)->assertJsonPath('0.venue_id', $this->venueA->id)->assertJsonPath('0.balance_after', 8);
        $this->pos($staff, 'GET', "pos/inventory/kpis?{$at}", $this->venueA)->assertOk()
            ->assertJson(['totalItems' => 8, 'totalValue' => 8 * 6000, 'lowStockCount' => 0, 'outOfStockCount' => 0]);
    }

    public function test_staff_cannot_read_another_venue(): void
    {
        $staff = $this->staffOf($this->venueA);
        $at = "venue_id={$this->venueB->id}";

        $this->pos($staff, 'GET', "pos/products?{$at}", $this->venueA)->assertForbidden();
        $this->pos($staff, 'GET', "pos/inventory/movements?{$at}", $this->venueA)->assertForbidden();
        $this->pos($staff, 'GET', "pos/inventory/kpis?{$at}", $this->venueA)->assertForbidden();
    }

    public function test_a_venue_of_another_organization_is_forbidden_even_to_an_owner(): void
    {
        $owner = $this->ownerOf($this->venueA->organization);
        $foreign = $this->openVenue();

        $this->pos($owner, 'GET', "pos/inventory/kpis?venue_id={$foreign->id}", $this->venueA)->assertForbidden();
    }

    public function test_low_stock_is_counted_per_venue(): void
    {
        $owner = $this->ownerOf($this->venueA->organization);
        $product = $this->product($this->venueA, ['min_stock' => 5]);
        $this->stockAt($this->venueA, $product, 3);
        $this->stockAt($this->venueB, $product, 30);
        $empty = $this->openVenue(['organization_id' => $this->venueA->organization_id]);

        $this->pos($owner, 'GET', "pos/inventory/kpis?venue_id={$this->venueA->id}", $this->venueA)->assertOk()->assertJson(['lowStockCount' => 1, 'totalItems' => 3]);
        $this->pos($owner, 'GET', "pos/inventory/kpis?venue_id={$this->venueB->id}", $this->venueA)->assertOk()->assertJson(['lowStockCount' => 0, 'totalItems' => 30]);
        $this->pos($owner, 'GET', "pos/inventory/kpis?venue_id={$empty->id}", $this->venueA)->assertOk()->assertJson(['outOfStockCount' => 1, 'totalItems' => 0]);
    }
}
