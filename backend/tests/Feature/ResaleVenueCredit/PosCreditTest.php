<?php

namespace Tests\Feature\ResaleVenueCredit;

use App\Models\Court;
use App\Models\Journal;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueCreditEntry;
use App\Services\Finance\VenueCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BooksCourts;
use Tests\Feature\PosVenueInventory\Concerns\SellsAtVenues;
use Tests\TestCase;

/** venue-credit "Venue credit as POS tender": credit pays an order alone or with other tenders, and a refund returns it. */
class PosCreditTest extends TestCase
{
    use BooksCourts;
    use RefreshDatabase;
    use SellsAtVenues;

    private Venue $venue;

    private User $cashier;

    private User $customer;

    private PosProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enablePos();
        $this->enableDirectPayments();
        $this->venue = $this->openVenue();
        $this->connectVenue($this->venue);
        $this->cashier = $this->staffOf($this->venue);
        $this->shiftAt($this->cashier, $this->venue);
        $this->customer = $this->customer();
        $this->product = $this->product($this->venue, ['tax_rate' => 0]);
        $this->stockAt($this->venue, $this->product, 10);
        app(VenueCreditService::class)->issue($this->venue->organization_id, $this->customer->id, 50000, 'entitlement_transfer', 'test-'.Str::uuid());
    }

    public function test_credit_pays_a_whole_order_as_revenue_through_its_own_journal(): void
    {
        $this->order(['payment_method' => 'credit'])->assertCreated()->assertJsonPath('order.status', 'completed');

        $this->assertSame(30000, $this->balance());
        $this->assertSame(8, $this->stockOf($this->venue, $this->product));
        $this->assertTrue(Journal::query()->withoutGlobalScopes()->where('event_type', 'venue_credit.spend')->exists());
        $this->assertFalse(Journal::query()->withoutGlobalScopes()->where('event_type', 'pos.sale')->exists());
    }

    public function test_cash_and_credit_together_post_only_the_cash_as_a_sale(): void
    {
        $this->cashAndCredit()->assertCreated();

        $this->assertSame(35000, $this->balance());
        $sale = Journal::query()->withoutGlobalScopes()->where('event_type', 'pos.sale')->sole();
        $this->assertSame(5000, (int) $sale->entries()->where('side', 'debit')->sum('amount'));
    }

    public function test_credit_needs_the_customer_and_enough_balance(): void
    {
        $this->order(['payment_method' => 'credit', 'customer_id' => null])->assertStatus(422);
        $this->order(['payment_method' => 'credit'], quantity: 6)->assertStatus(422)->assertJsonPath('message', 'Số dư credit không đủ.');

        $this->assertSame(0, PosOrder::query()->withoutGlobalScopes()->count());
        $this->assertSame(50000, $this->balance());
        $this->assertSame(10, $this->stockOf($this->venue, $this->product));
    }

    public function test_a_qr_order_spends_its_credit_only_when_the_transfer_arrives(): void
    {
        $reference = $this->qrWithCredit(15000)->json('order.payment_reference');
        $this->assertSame(50000, $this->balance());

        $this->transfer($this->court(), $reference, 5000)->assertOk();

        $order = PosOrder::query()->withoutGlobalScopes()->sole();
        $this->assertSame('completed', $order->status);
        $this->assertSame(35000, $this->balance());
        $this->assertSame(['paid', 'paid'], $order->payments()->pluck('status')->all());
    }

    public function test_a_qr_order_whose_credit_was_spent_meanwhile_is_not_completed(): void
    {
        $reference = $this->qrWithCredit(45000, quantity: 5)->json('order.payment_reference');
        app(VenueCreditService::class)->spend($this->venue->organization_id, $this->customer->id, 10000, 'pos_order', 'elsewhere');

        $this->transfer($this->court(), $reference, 5000)->assertOk();

        $this->assertSame('pending_payment', PosOrder::query()->withoutGlobalScopes()->sole()->status);
        $this->assertSame(40000, $this->balance());
        $this->assertSame(10, $this->stockOf($this->venue, $this->product));
    }

    public function test_a_refund_to_the_original_method_returns_the_credit_part_as_credit(): void
    {
        $orderId = $this->cashAndCredit()->assertCreated()->json('order.id');

        $this->pos($this->ownerOf($this->venue->organization), 'POST', "pos/orders/{$orderId}/refund", $this->venue, ['refund_method' => 'original_method'])->assertOk();

        $this->assertSame(50000, $this->balance());
        $this->assertSame(15000, (int) VenueCreditEntry::query()->withoutGlobalScopes()->where('type', 'return')->sole()->balance_delta);
    }

    /** @param  array<string, mixed>  $payment */
    private function order(array $payment, int $quantity = 2): TestResponse
    {
        return $this->pos($this->cashier, 'POST', 'pos/orders', $this->venue, $payment + [
            'items' => [['product_id' => $this->product->id, 'quantity' => $quantity]],
            'customer_id' => $this->customer->id,
        ]);
    }

    private function cashAndCredit(): TestResponse
    {
        return $this->order(['payment_method' => 'mixed', 'allow_mixed_tender' => true, 'tenders' => [
            ['method' => 'cash', 'amount' => 5000], ['method' => 'credit', 'amount' => 15000],
        ]]);
    }

    /** Each unit is 10,000; credit pays $credit and the venue's QR the rest. */
    private function qrWithCredit(int $credit, int $quantity = 2): TestResponse
    {
        return $this->order(['payment_method' => 'mixed', 'allow_mixed_tender' => true, 'tenders' => [
            ['method' => 'qr', 'amount' => 10000 * $quantity - $credit], ['method' => 'credit', 'amount' => $credit],
        ]], $quantity)->assertCreated()->assertJsonPath('order.status', 'pending_payment');
    }

    private function court(): Court
    {
        return $this->activeCourt($this->venue);
    }

    private function balance(): int
    {
        return app(VenueCreditService::class)->balance($this->venue->organization_id, $this->customer->id);
    }
}
