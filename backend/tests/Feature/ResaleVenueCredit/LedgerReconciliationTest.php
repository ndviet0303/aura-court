<?php

namespace Tests\Feature\ResaleVenueCredit;

use App\Models\Organization;
use App\Models\VenueCreditAccount;
use App\Models\VenueCreditEntry;
use App\Services\Finance\VenueCreditCashOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PosVenueInventory\Concerns\SellsAtVenues;
use Tests\Feature\ResaleVenueCredit\Concerns\SellsSlots;
use Tests\TestCase;

/** venue-credit: balances plus reserved credit always equal the owner's credit liability and the sum of the entries. */
class LedgerReconciliationTest extends TestCase
{
    use RefreshDatabase;
    use SellsAtVenues;
    use SellsSlots;

    public function test_every_credit_move_keeps_the_ledger_reconciled(): void
    {
        $court = $this->resaleCourt();
        $this->enablePos();
        $venue = $court->venue;
        $organizationId = (int) $venue->organization_id;
        $seller = $this->customer();
        $listing = $this->listSlot($this->paidSlot($court, $seller), $seller, 80000);
        $this->transfer($court, $this->hold($listing, $this->customer())->assertOk()->json('payment.reference'), 80000)->assertOk();
        $this->assertReconciled($organizationId, 80000);

        // reserve then release
        $released = $this->checkout($seller, $court, '2026-11-06 18:00', '2026-11-06 19:00', 100000, ['venue_credit_amount' => 30000])->assertCreated()->json('reservation.code');
        $this->assertReconciled($organizationId, 80000);
        $this->resale($seller, "reservations/{$released}/cancel-payment", $organizationId)->assertOk();
        $this->assertReconciled($organizationId, 80000);

        // reserve then capture on the QR receipt
        $captured = $this->checkout($seller, $court, '2026-11-07 18:00', '2026-11-07 19:00', 100000, ['venue_credit_amount' => 20000])->assertCreated()->json('reservation.code');
        $this->transfer($court, $this->paymentCode($captured), 80000)->assertOk();
        $this->assertReconciled($organizationId, 60000);

        // spend at POS, then return it with a refund
        $cashier = $this->staffOf($venue);
        $shift = $this->shiftAt($cashier, $venue);
        $product = $this->product($venue, ['category' => 'services', 'tax_rate' => 0]);
        $orderId = $this->pos($cashier, 'POST', 'pos/orders', $venue, ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'payment_method' => 'credit', 'customer_id' => $seller->id])
            ->assertCreated()->json('order.id');
        $this->assertReconciled($organizationId, 50000);
        $this->pos($this->ownerOf($venue->organization), 'POST', "pos/orders/{$orderId}/refund", $venue, ['refund_method' => 'original_method'])->assertOk();
        $this->assertReconciled($organizationId, 60000);

        // cash out at the counter
        $cashOuts = app(VenueCreditCashOutService::class);
        $cashOuts->confirm($shift, $cashier, $cashOuts->create($seller, Organization::query()->findOrFail($organizationId), 20000)['code']);
        $this->assertReconciled($organizationId, 40000);

        $this->assertEqualsCanonicalizing(['issue', 'reserve', 'release', 'capture', 'spend', 'return', 'cash_out'], VenueCreditEntry::query()->withoutGlobalScopes()->distinct()->pluck('type')->all());
    }

    private function assertReconciled(int $organizationId, int $owed): void
    {
        $accounts = VenueCreditAccount::query()->withoutGlobalScopes()->where('organization_id', $organizationId);
        $entries = VenueCreditEntry::query()->withoutGlobalScopes()->where('organization_id', $organizationId);
        $liability = (int) DB::table('journal_entries')->join('accounts', 'accounts.id', '=', 'journal_entries.account_id')
            ->where('accounts.code', 'owner_credit_liability')->where('accounts.organization_id', $organizationId)
            ->selectRaw("COALESCE(SUM(CASE WHEN side = 'credit' THEN amount ELSE -amount END), 0) AS owed")->value('owed');

        $this->assertSame(
            [$owed, $owed, $owed],
            [(int) $accounts->sum(DB::raw('balance + reserved')), $liability, (int) $entries->sum(DB::raw('balance_delta + reserved_delta'))],
        );
    }
}
