<?php

namespace Tests\Feature\PosVenueInventory;

use App\Models\PosOrder;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Module02\Concerns\SeedsBookings;
use Tests\Feature\PosVenueInventory\Concerns\SellsAtVenues;
use Tests\TestCase;

/** pos-venue-inventory: what QR orders and tabs hold at a venue is not for sale there, and only there. */
class VenueStockHoldTest extends TestCase
{
    use RefreshDatabase;
    use SeedsBookings;
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

    public function test_a_qr_hold_at_one_venue_does_not_stop_a_sale_at_another(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 1);
        $this->stockAt($this->venueB, $product, 1);
        $this->pendingQrOrder($this->staffOf($this->venueA), $this->venueA, $product, 1);
        $cashierA = $this->staffOf($this->venueA);
        $cashierB = $this->staffOf($this->venueB);
        $this->shiftAt($cashierA, $this->venueA);
        $this->shiftAt($cashierB, $this->venueB);

        $this->pos($cashierA, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product))
            ->assertStatus(409)->assertJsonPath('message', "Insufficient stock for {$product->sku} (1 reserved by pending orders/tabs).");
        $this->pos($cashierB, 'POST', 'pos/orders', $this->venueB, $this->cashOrder($product))->assertCreated();

        $this->assertSame(1, $this->stockOf($this->venueA, $product));
        $this->assertSame(0, $this->stockOf($this->venueB, $product));
    }

    public function test_a_tab_holds_stock_of_its_booking_venue_only(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 2);
        $this->stockAt($this->venueB, $product, 2);
        $staff = $this->staffOf($this->venueA, $this->venueB);
        $bookingA = $this->booking($this->activeCourt($this->venueA), $this->tomorrowAt(10));
        $bookingB = $this->booking($this->activeCourt($this->venueB), $this->tomorrowAt(10));

        $this->pos($staff, 'POST', 'service-tabs', $this->venueA, ['reservation_id' => $bookingA->id, 'items' => [['product_id' => $product->id, 'quantity' => 2]]])->assertCreated();
        $this->pos($staff, 'POST', 'service-tabs', $this->venueA, ['reservation_id' => $bookingA->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertStatus(409)->assertJsonPath('message', "Insufficient stock for {$product->name}. (Available: 0, requested: 1)");
        $this->pos($staff, 'POST', 'service-tabs', $this->venueB, ['reservation_id' => $bookingB->id, 'items' => [['product_id' => $product->id, 'quantity' => 2]]])->assertCreated();
    }

    public function test_an_untracked_service_goes_on_a_tab_and_settles_without_stock(): void
    {
        $service = $this->product($this->venueA, ['category' => 'services']);
        $staff = $this->staffOf($this->venueA);
        $this->shiftAt($staff, $this->venueA);
        $booking = $this->booking($this->activeCourt($this->venueA), $this->tomorrowAt(10));

        $tab = $this->pos($staff, 'POST', 'service-tabs', $this->venueA, ['reservation_id' => $booking->id, 'items' => [['product_id' => $service->id, 'quantity' => 2]]])
            ->assertCreated()->json('tab.id');
        $this->pos($staff, 'POST', "service-tabs/{$tab}/settle", $this->venueA, ['payment_method' => 'cash'])->assertOk();

        $this->assertSame(0, $this->stockOf($this->venueA, $service));
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_settled_tab_deducts_at_its_booking_venue(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 5);
        $this->stockAt($this->venueB, $product, 5);
        $staff = $this->staffOf($this->venueB);
        $this->shiftAt($staff, $this->venueB);
        $booking = $this->booking($this->activeCourt($this->venueB), $this->tomorrowAt(10));
        $tab = $this->pos($staff, 'POST', 'service-tabs', $this->venueB, ['reservation_id' => $booking->id, 'items' => [['product_id' => $product->id, 'quantity' => 2]]])
            ->assertCreated()->json('tab.id');

        $this->pos($staff, 'POST', "service-tabs/{$tab}/settle", $this->venueB, ['payment_method' => 'cash'])->assertOk();

        $this->assertSame(5, $this->stockOf($this->venueA, $product));
        $this->assertSame(3, $this->stockOf($this->venueB, $product));
    }

    public function test_the_second_request_for_the_last_unit_fails_naming_the_product(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 1);
        $first = $this->staffOf($this->venueA);
        $second = $this->staffOf($this->venueA);
        $this->shiftAt($first, $this->venueA);
        $this->shiftAt($second, $this->venueA);

        $this->pos($first, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product))->assertCreated();
        $this->pos($second, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product))
            ->assertStatus(409)->assertJsonPath('message', "Insufficient stock for {$product->sku}.");

        $this->assertSame(1, PosOrder::count());
        $this->assertSame(0, $this->stockOf($this->venueA, $product));
    }

    public function test_a_sale_locks_the_venue_stock_row_before_checking_it(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 3);
        $cashier = $this->staffOf($this->venueA);
        $this->shiftAt($cashier, $this->venueA);

        DB::enableQueryLog();
        $this->pos($cashier, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product))->assertCreated();
        $queries = collect(DB::getQueryLog())->pluck('query');

        $lock = $queries->search(fn (string $sql) => Str::contains($sql, 'from "venue_stocks"') && Str::endsWith($sql, 'for update'));
        $this->assertNotFalse($lock, 'The sale did not lock its venue stock row.');
        $this->assertGreaterThan($lock, $queries->search(fn (string $sql) => Str::startsWith($sql, 'update "venue_stocks"')));
    }
}
