<?php

namespace Tests\Feature\PosVenueInventory;

use App\Models\OwnerPaymentAllocation;
use App\Models\PosOrder;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueStock;
use App\Services\POS\PosQrPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\PosVenueInventory\Concerns\SellsAtVenues;
use Tests\TestCase;

/** pos-venue-inventory: venue-scoped stock balance, and every stock event bound to one venue. */
class VenueStockBalanceTest extends TestCase
{
    use RefreshDatabase;
    use SellsAtVenues;

    private Venue $venueA;

    private Venue $venueB;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePos();
        $this->venueA = $this->openVenue(['name' => ['vi' => 'Cầu Giấy']]);
        $this->venueB = $this->openVenue(['organization_id' => $this->venueA->organization_id, 'name' => ['vi' => 'Đống Đa']]);
        $this->owner = $this->ownerOf($this->venueA->organization);
    }

    public function test_a_sale_at_one_venue_leaves_the_other_venue_unchanged(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 10);
        $this->stockAt($this->venueB, $product, 10);
        $cashier = $this->staffOf($this->venueA);
        $this->shiftAt($cashier, $this->venueA);

        $orderId = $this->pos($cashier, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product, 2))->assertCreated()->json('order.id');

        $this->assertSame(8, $this->stockOf($this->venueA, $product));
        $this->assertSame(10, $this->stockOf($this->venueB, $product));
        $sale = StockMovement::query()->where('reference_id', $orderId)->sole();
        $this->assertSame([$this->venueA->id, -2, 8], [$sale->venue_id, $sale->quantity_delta, $sale->balance_after]);
    }

    public function test_products_report_the_stock_of_the_venue_read(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 10);
        $newVenue = $this->openVenue(['organization_id' => $this->venueA->organization_id]);

        $this->pos($this->owner, 'GET', "pos/products?venue_id={$this->venueA->id}", $this->venueA)->assertOk()->assertJsonPath('0.stock_quantity', 10);
        $this->pos($this->owner, 'GET', "pos/products?venue_id={$newVenue->id}", $this->venueA)->assertOk()->assertJsonPath('0.stock_quantity', 0);
        // An owner of several venues with no shift and no venue asked gets the catalog only.
        $this->pos($this->owner, 'GET', 'pos/products', $this->venueA)->assertOk()->assertJsonPath('0.stock_quantity', null);

        $cashier = $this->staffOf($this->venueA, $this->venueB);
        $this->shiftAt($cashier, $this->venueA);
        $this->pos($cashier, 'GET', 'pos/products', $this->venueA)->assertOk()->assertJsonPath('0.stock_quantity', 10);
    }

    public function test_an_untracked_service_sells_without_touching_stock(): void
    {
        $service = $this->product($this->venueA, ['category' => 'services']);
        $cashier = $this->staffOf($this->venueA);
        $this->shiftAt($cashier, $this->venueA);

        $this->pos($cashier, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($service, 3))->assertCreated();

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, VenueStock::count());
    }

    public function test_a_receipt_raises_only_the_chosen_venue(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 10);

        $this->pos($this->owner, 'POST', "pos/products/{$product->id}/stock", $this->venueA, ['venue_id' => $this->venueB->id, 'type' => 'in', 'quantity' => 24])
            ->assertOk()->assertJsonPath('product.stock_quantity', 24);

        $this->assertSame(10, $this->stockOf($this->venueA, $product));
        $this->assertSame(24, $this->stockOf($this->venueB, $product));
        $receipt = StockMovement::query()->sole();
        $this->assertSame([$this->venueB->id, $this->owner->id, 24, 24], [$receipt->venue_id, $receipt->created_by, $receipt->quantity_delta, $receipt->balance_after]);
    }

    public function test_a_count_sets_the_venue_stock_to_the_counted_quantity(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 10);
        $this->stockAt($this->venueB, $product, 4);

        $this->pos($this->owner, 'POST', "pos/products/{$product->id}/stock", $this->venueA, ['venue_id' => $this->venueB->id, 'type' => 'adjustment', 'quantity' => 7])
            ->assertOk()->assertJsonPath('product.stock_quantity', 7);

        $this->assertSame(10, $this->stockOf($this->venueA, $product));
        $this->assertSame(3, (int) StockMovement::query()->sole()->quantity_delta);
    }

    public function test_a_stock_change_without_a_venue_is_refused_unchanged(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 10);

        $this->pos($this->owner, 'POST', "pos/products/{$product->id}/stock", $this->venueA, ['type' => 'in', 'quantity' => 5])
            ->assertUnprocessable()->assertJsonValidationErrors('venue_id');
        $this->pos($this->owner, 'POST', 'pos/products', $this->venueA, ['sku' => 'NEW-1', 'name' => 'Nước tăng lực', 'price' => 15000, 'stock_quantity' => 12])
            ->assertUnprocessable();

        $this->assertSame(10, $this->stockOf($this->venueA, $product));
        $this->assertSame(0, StockMovement::count());
        $this->assertDatabaseMissing('pos_products', ['sku' => 'NEW-1']);
    }

    public function test_opening_stock_lands_at_the_named_venue(): void
    {
        $productId = $this->pos($this->owner, 'POST', 'pos/products', $this->venueA, ['sku' => 'NEW-2', 'name' => 'Nước tăng lực', 'price' => 15000, 'stock_quantity' => 12, 'venue_id' => $this->venueB->id])
            ->assertCreated()->assertJsonPath('product.stock_quantity', 12)->json('product.id');

        $this->assertSame([$this->venueB->id => 12], VenueStock::query()->where('product_id', $productId)->pluck('quantity', 'venue_id')->all());
        $this->assertSame('initial_stock', StockMovement::query()->sole()->reference_type);
    }

    public function test_a_return_goes_back_to_the_venue_of_the_sale(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 10);
        $this->stockAt($this->venueB, $product, 10);
        $cashier = $this->staffOf($this->venueA);
        $this->shiftAt($cashier, $this->venueA);
        $orderId = $this->pos($cashier, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product, 2))->assertCreated()->json('order.id');
        $this->shiftAt($this->owner, $this->venueB);

        $this->pos($this->owner, 'POST', "pos/orders/{$orderId}/refund", $this->venueA, ['items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertOk();

        $this->assertSame(9, $this->stockOf($this->venueA, $product));
        $this->assertSame(10, $this->stockOf($this->venueB, $product));
        $this->assertSame($this->venueA->id, StockMovement::query()->where('type', 'return')->sole()->venue_id);
    }

    public function test_an_order_without_a_venue_refunds_only_without_restocking(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 10);
        $cashier = $this->staffOf($this->venueA);
        $this->shiftAt($cashier, $this->venueA);
        $orderId = $this->pos($cashier, 'POST', 'pos/orders', $this->venueA, $this->cashOrder($product, 2))->assertCreated()->json('order.id');
        PosOrder::query()->whereKey($orderId)->update(['venue_id' => null]);

        $this->pos($this->owner, 'POST', "pos/orders/{$orderId}/refund", $this->venueA, ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertUnprocessable();
        $this->pos($this->owner, 'POST', "pos/orders/{$orderId}/refund", $this->venueA, ['items' => [['product_id' => $product->id, 'quantity' => 1, 'restock' => false]]])
            ->assertOk();

        $this->assertSame(8, $this->stockOf($this->venueA, $product));
        $this->assertSame(0, StockMovement::query()->where('type', 'return')->count());
    }

    public function test_a_late_qr_confirmation_deducts_once_at_the_order_venue(): void
    {
        $product = $this->product($this->venueA);
        $this->stockAt($this->venueA, $product, 10);
        $this->stockAt($this->venueB, $product, 10);
        $order = $this->pendingQrOrder($this->staffOf($this->venueA), $this->venueA, $product, 3);

        app(PosQrPaymentService::class)->confirmPaidAttempt((new OwnerPaymentAllocation)->forceFill(['purpose_id' => $order->id, 'amount' => $order->total_amount]), now());

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(7, $this->stockOf($this->venueA, $product));
        $this->assertSame(10, $this->stockOf($this->venueB, $product));
        $this->assertSame($this->venueA->id, StockMovement::query()->sole()->venue_id);
    }
}
