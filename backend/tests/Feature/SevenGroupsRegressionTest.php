<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CashShift;
use App\Models\Court;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\PosProduct;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PricesCourts;
use Tests\TestCase;

class SevenGroupsRegressionTest extends TestCase
{
    use PricesCourts;
    use RefreshDatabase;

    /**
     * Task 1.2 Reproduction: Unpaid V2 confirmation.
     * Demonstrates that V2 confirm endpoint confirms held reservations and entitlements
     * without verifying or debiting the user's wallet, allowing a 0-balance user to confirm for free.
     */
    public function test_reproduce_unpaid_v2_confirmation_confirms_without_wallet_debit(): void
    {
        [$organization, $customer, $court] = $this->tenant();
        $date = now(config('app.timezone'))->addDays(3)->setTime(18, 0);

        // Price the court at 200,000 VND per hour
        $this->priceCourt($court, 200000);

        // Customer wallet has 0 VND balance
        $wallet = WalletAccount::create([
            'organization_id' => $organization->id,
            'user_id' => $customer->id,
            'currency' => 'VND',
            'balance' => 0,
        ]);

        $headers = [
            'X-Organization-ID' => (string) $organization->id,
        ];

        // 1. Get quote
        $quoteRes = $this->actingAs($customer)->withHeaders($headers + ['Idempotency-Key' => 'quote-unpaid-1'])
            ->postJson('/api/price-quotes', [
                'court_id' => $court->id,
                'slots' => [$date->toIso8601String()],
            ]);
        $quoteRes->assertCreated();
        $quoteId = $quoteRes->json('quote.id');
        $this->assertSame(200000, $quoteRes->json('quote.total_amount'));

        // 2. Hold reservation
        $holdRes = $this->actingAs($customer)->withHeaders($headers + ['Idempotency-Key' => 'hold-unpaid-1'])
            ->postJson('/api/reservations', [
                'quote_id' => $quoteId,
                'expected_total' => $quoteRes->json('quote.total_amount'),
            ]);
        $holdRes->assertCreated();
        $code = $holdRes->json('reservation.code');
        $this->assertSame('held', $holdRes->json('reservation.status'));

        // 3. Confirm reservation
        // 3. Confirm reservation without funds -> rejected with 409 Conflict
        $confirmRes = $this->actingAs($customer)->withHeaders($headers + ['Idempotency-Key' => 'confirm-unpaid-1'])
            ->postJson("/api/reservations/{$code}/confirm");

        // Confirmation is rejected because wallet balance is 0 VND:
        $confirmRes->assertStatus(409);
        $reservation = Reservation::where('code', $code)->firstOrFail();
        $this->assertSame('held', $reservation->status);

        // When wallet is funded, confirmation succeeds and debits the wallet atomically:
        $walletService = app(WalletPaymentService::class);
        $walletService->credit($wallet, 500000, 'test_topup', 1, 'Topup for confirm');

        $confirmPaidRes = $this->actingAs($customer)->withHeaders($headers + ['Idempotency-Key' => 'confirm-paid-1'])
            ->postJson("/api/reservations/{$code}/confirm");
        $confirmPaidRes->assertOk();
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame('confirmed', $reservation->slots()->first()->status);
        $this->assertSame('active', $reservation->entitlements()->first()->status);
        $this->assertSame(300000, $wallet->fresh()->balance);
        $this->assertDatabaseHas('wallet_transactions', ['wallet_account_id' => $wallet->id, 'type' => 'debit', 'source_type' => 'reservation_payment']);
    }

    /**
     * Task 1.2 Reproduction: Wrong reservation identity.
     * Demonstrates that passing a V1 Booking ID as reservation_id in POS orders
     * triggers a foreign key violation (pos_orders_reservation_id_foreign) because
     * pos_orders.reservation_id references reservations.id, not bookings.id.
     */
    public function test_reproduce_pos_wrong_reservation_identity_with_v1_booking_id(): void
    {
        [$organization, $customer, $court, $cashier] = $this->tenantWithCashier();

        // Create a product
        $product = PosProduct::create([
            'organization_id' => $organization->id,
            'sku' => 'SHUTTLE-TEST',
            'name' => 'Shuttle Test',
            'price' => 50000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        // Create a V1 Booking with an ID that does NOT exist in V2 reservations
        $booking = Booking::create([
            'code' => 'BK-V1-IDENTITY-TEST',
            'user_id' => $customer->id,
            'court_id' => $court->id,
            'total_amount' => 150000,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseMissing('reservations', ['id' => $booking->id]);

        // POS request passes the V1 booking ID as reservation_id (just like PosView.vue does)
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'reservation_id' => $booking->id, // V1 booking ID
        ];

        // DEFECT REPRODUCTION:
        // Because reservation_id references 'reservations.id', submitting V1 booking ID
        // throws a 500 error due to SQLSTATE[23503] foreign key violation!
        $response = $this->actingAs($cashier)
            ->withHeader('X-Organization-ID', (string) $organization->id)
            ->withHeader('Idempotency-Key', 'pos-wrong-identity-1')
            ->postJson('/api/pos/orders', $payload);

        // Domain validation returns 422 unprocessable instead of unhandled 500 foreign key crash
        $response->assertUnprocessable();
    }

    /**
     * Task 1.2 Reproduction: POS false success backend reality.
     * Demonstrates that when an order is rejected (e.g. stock shortfall),
     * the backend transaction aborts and persists zero order/payment/stock records.
     */
    public function test_reproduce_pos_false_success_backend_order_rejection_leaves_no_financial_records(): void
    {
        [$organization, $customer, $court, $cashier] = $this->tenantWithCashier();

        $product = PosProduct::create([
            'organization_id' => $organization->id,
            'sku' => 'LIMITED-ITEM',
            'name' => 'Limited Product',
            'price' => 100000,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        // Request 5 items when only 2 are in stock
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payment_method' => 'cash',
        ];

        $response = $this->actingAs($cashier)
            ->withHeader('X-Organization-ID', (string) $organization->id)
            ->withHeader('Idempotency-Key', 'pos-stock-shortfall-1')
            ->postJson('/api/pos/orders', $payload);

        // Backend rejects with 409 Conflict
        $response->assertStatus(409);

        // DEFECT REPRODUCTION:
        // Backend stores NO orders and NO stock movements.
        // (Frontend catch block would falsely claim success and show a fake receipt).
        $this->assertDatabaseCount('pos_orders', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    /**
     * Task 1.2 Reproduction: Local tournament registration.
     * Demonstrates that there is no customer tournament self-registration / wallet checkout endpoint.
     */
    public function test_reproduce_tournament_lacks_customer_registration_and_payment_endpoint(): void
    {
        [$organization, $customer] = $this->tenant();

        // Customer attempts to self-register / pay fee for tournament
        $response = $this->actingAs($customer)
            ->withHeader('X-Organization-ID', (string) $organization->id)
            ->postJson('/api/tournaments/1/register', [
                'name' => 'Athlete Customer',
                'phone' => '0901234567',
            ]);

        // DEFECT REPRODUCTION: 404 Route Not Found
        $response->assertNotFound();
    }

    private function tenant(): array
    {
        $organization = Organization::create([
            'name' => 'Regression Tenant',
            'slug' => 'reg-tenant-'.uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $user = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => OrganizationMember::ROLE_CUSTOMER, 'status' => 'active']);
        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Reg Venue',
            'slug' => 'reg-venue-'.uniqid(),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Reg Court', 'is_active' => true]);

        config(['features.pilot_organizations' => [(string) $organization->id], 'features.flags.tenant_pos_and_wallet' => true]);

        return [$organization, $user, $court];
    }

    private function tenantWithCashier(): array
    {
        [$organization, $customer, $court] = $this->tenant();
        $cashier = User::factory()->create();
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $cashier->id, 'role' => 'staff', 'status' => 'active']);
        $this->assignStaffVenues($organization, $court->venue);
        CashShift::create([
            'organization_id' => $organization->id,
            'cashier_id' => $cashier->id,
            'opening_cash' => 100000,
            'opened_at' => now(),
            'status' => 'open',
        ]);

        return [$organization, $customer, $court, $cashier];
    }
}
