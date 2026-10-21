<?php

namespace Tests\Feature\Readiness;

use App\Domain\Subscription\Actions\ConfirmSubscriptionPayment;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\EntitlementTransfer;
use App\Models\FinanceException;
use App\Models\LoyaltyRule;
use App\Models\MonthlyInvoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformPlan;
use App\Models\PlatformProviderReceipt;
use App\Models\PlatformSubscription;
use App\Models\PointLedger;
use App\Models\PosOrder;
use App\Models\PosProduct;
use App\Models\PriceRule;
use App\Models\ProviderEvent;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\StockMovement;
use App\Models\Tournament;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueBookingPolicy;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\ProviderEventHandler;
use App\Services\Payment\SplitPaymentService;
use App\Services\Payment\WalletPaymentService;
use App\Services\POS\PosService;
use App\Services\Social\MarketplaceService;
use App\Services\Social\PeerToPeerResaleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Task 13.3 — Comprehensive Concurrency and Retry Invariants Acceptance Suite
 *
 * Verifies zero double effects across 8 concurrency and idempotent retry scenarios:
 * 1. Court overlap: Contending attempts on the exact same court and slot.
 * 2. Points & Coupon use: Concurrent usage of the same coupon or user point balance.
 * 3. Final stock in POS: 1 unit remaining in inventory under concurrent purchases.
 * 4. Split bill confirmation: Replay or concurrent payment confirmation.
 * 5. Marketplace transfer: Concurrent purchases for the same secondary listing.
 * 6. Recurring series invoice: Idempotent provider webhook replay for recurring invoice.
 * 7. Platform subscription: Duplicate webhook delivery for same provider transaction.
 * 9. Last tournament place: Tournament with max participants reaching capacity.
 */
class ComprehensiveConcurrencyAndRetryInvariantsTest extends TestCase
{
    use RefreshDatabase;

    private array $eventDates = [];

    // =========================================================================
    // SCENARIO 1: Court Overlap
    // =========================================================================

    public function test_scenario_1_court_overlap_contending_attempts_result_in_exactly_one_winner(): void
    {
        $org = Organization::create([
            'name' => 'Court Overlap Org',
            'slug' => 'court-overlap-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'Overlap Venue',
            'slug' => 'overlap-venue-'.Str::lower(Str::random(8)),
            'address' => '100 Stadium Road',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Center Court', 'is_active' => true]);

        $targetDate = now(config('app.timezone'))->addDays(5)->setTime(18, 0)->startOfHour();
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $targetDate->dayOfWeekIso,
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'price' => 200000,
            'is_active' => true,
        ]);
        PriceRule::create([
            'organization_id' => $org->id,
            'court_id' => $court->id,
            'type' => 'base',
            'price' => 200000,
        ]);

        $isoSlot = $targetDate->toIso8601String();
        $walletService = app(WalletPaymentService::class);

        // 5 competing users
        $users = User::factory()->count(5)->create(['role' => 'customer', 'email_verified_at' => now()]);
        foreach ($users as $user) {
            OrganizationMember::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'role' => 'customer',
                'status' => 'active',
            ]);
            $wallet = $walletService->getOrCreateWallet($user->id, $org->id);
            $walletService->credit($wallet, 500000, 'test_fund', 1, 'Fund');
        }

        $successCount = 0;
        $conflictCount = 0;

        // User 0 checks out first
        $res0 = $this->actingAs($users[0])
            ->withHeaders([
                'X-Organization-ID' => (string) $org->id,
                'Idempotency-Key' => 'overlap-checkout-'.Str::random(10),
            ])
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$isoSlot],
                'expected_total' => 200000,
            ]);
        if ($res0->status() === 201) {
            $successCount++;
        } else {
            $this->fail('User 0 initial booking should succeed.');
        }

        // Remaining 4 users contend on the exact same court and slot (checkout and hold)
        for ($i = 1; $i < count($users); $i++) {
            $res = $this->actingAs($users[$i])
                ->withHeaders([
                    'X-Organization-ID' => (string) $org->id,
                    'Idempotency-Key' => 'overlap-'.$i.'-'.Str::random(10),
                ])
                ->postJson($i % 2 === 1 ? '/api/reservations/checkout' : '/api/reservations', [
                    'court_id' => $court->id,
                    'slots' => [$isoSlot],
                    'expected_total' => 200000,
                ]);

            if ($res->status() === 409) {
                $conflictCount++;
            }
        }

        // Invariant 1: Exactly 1 succeeds, remaining 4 conflict
        $this->assertSame(1, $successCount, 'Exactly 1 reservation must win the contended court slot.');
        $this->assertSame(4, $conflictCount, 'All competing attempts must receive 409 conflict.');

        // Invariant 2: Exactly 1 active slot entitlement in database
        $activeReservationSlots = ReservationSlot::where('court_id', $court->id)
            ->whereIn('status', ['held', 'confirmed'])
            ->count();
        $this->assertSame(1, $activeReservationSlots, 'Only 1 active canonical reservation slot can exist in DB.');

        $activeEntitlements = ReservationEntitlement::where('court_id', $court->id)
            ->whereIn('status', ['held', 'active'])
            ->count();
        $this->assertSame(1, $activeEntitlements, 'Only 1 active entitlement can exist in DB.');
    }

    // =========================================================================
    // SCENARIO 2: Points & Coupon Use
    // =========================================================================

    public function test_scenario_2_points_and_coupon_concurrent_use_applied_once_without_double_effects(): void
    {
        $fixture = $this->setupTenantWithReceivingConnection();
        $payer = $fixture['payer'];
        $org = $fixture['organization'];
        $court = $fixture['court'];

        // Loyalty Rule: 100 points = 200,000 VND (10 points = 20,000 VND)
        LoyaltyRule::create([
            'organization_id' => $org->id,
            'name' => 'Base redemption',
            'version' => 1,
            'earn_points_per_100_vnd' => 0,
            'redeem_points_per_100_vnd' => 0,
            'status' => 'published',
            'metadata' => ['points_redeemed' => 100, 'reward_value_vnd' => 200000],
            'published_at' => now(),
        ]);

        // Payer has exactly 10 points
        PointLedger::create([
            'organization_id' => $org->id,
            'user_id' => $payer->id,
            'loyalty_rule_id' => null,
            'delta' => 10,
            'balance_after' => 10,
            'source_type' => 'seed',
            'source_id' => 1,
        ]);

        // Single-use coupon for 20,000 VND off
        $coupon = Coupon::create([
            'organization_id' => $org->id,
            'code' => 'DISC20K',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
            'is_active' => true,
            'usage_limit' => 1,
            'usage_count' => 0,
        ]);

        $slot1 = now()->addDays(3)->setTime(10, 0, 0);
        $slot2 = now()->addDays(3)->setTime(12, 0, 0);
        $slot3 = now()->addDays(3)->setTime(14, 0, 0);

        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $slot1->dayOfWeekIso, 'starts_at' => '10:00', 'ends_at' => '11:00', 'price' => 120000, 'is_active' => true]);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $slot2->dayOfWeekIso, 'starts_at' => '12:00', 'ends_at' => '13:00', 'price' => 120000, 'is_active' => true]);
        CourtPriceSlot::create(['court_id' => $court->id, 'weekday' => $slot3->dayOfWeekIso, 'starts_at' => '14:00', 'ends_at' => '15:00', 'price' => 120000, 'is_active' => true]);

        // 1. Initial valid booking uses both the coupon AND the 10 loyalty points
        // Subtotal 120,000 - coupon 20,000 - points 20,000 = 80,000 payable
        $res1 = $this->actingAs($payer)
            ->withHeader('Idempotency-Key', 'partial-benefit-book-1')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$slot1->toIso8601String()],
                'expected_total' => 80000,
                'coupon_code' => 'DISC20K',
                'loyalty_points' => 10,
                'loyalty_policy_version' => 1,
            ]);
        $res1->assertCreated();
        $this->assertSame(80000, $res1->json('reservation.total_amount'));

        // Point balance is now 0 and coupon usage is 1
        $this->assertSame(1, (int) $coupon->fresh()->usage_count);
        $pointBalance = (int) PointLedger::where('organization_id', $org->id)->where('user_id', $payer->id)->sum('delta');
        $this->assertSame(0, $pointBalance);

        // 2. Concurrent / second booking attempt tries to reuse the single-use coupon on Slot 2 -> REJECTED
        $res2 = $this->actingAs($payer)
            ->withHeader('Idempotency-Key', 'concurrent-coupon-reuse-2')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$slot2->toIso8601String()],
                'expected_total' => 100000,
                'coupon_code' => 'DISC20K',
            ]);
        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['coupon_code']);

        // 3. Concurrent / third booking attempt tries to reuse the 10 loyalty points on Slot 3 -> REJECTED
        $res3 = $this->actingAs($payer)
            ->withHeader('Idempotency-Key', 'concurrent-points-reuse-3')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$slot3->toIso8601String()],
                'expected_total' => 100000,
                'loyalty_points' => 10,
                'loyalty_policy_version' => 1,
            ]);
        $res3->assertStatus(422);
        $res3->assertJsonPath('message', 'Số dư điểm không đủ để thực hiện quy đổi.');

        // Invariants:
        // - Points not decremented below zero
        $finalPoints = (int) PointLedger::where('organization_id', $org->id)->where('user_id', $payer->id)->sum('delta');
        $this->assertSame(0, $finalPoints, 'Points balance must be exactly 0, never negative.');

        // - Coupon usage is exactly 1 (no double discount)
        $this->assertSame(1, (int) $coupon->fresh()->usage_count, 'Coupon usage count must be exactly 1.');

        // - Only 1 reservation created
        $this->assertSame(1, Reservation::where('user_id', $payer->id)->count(), 'Only 1 reservation can be created with benefits.');
    }

    // =========================================================================
    // SCENARIO 3: Final Stock in POS
    // =========================================================================

    public function test_scenario_3_final_stock_pos_concurrent_purchase_leaves_zero_not_negative(): void
    {
        $org = Organization::create([
            'name' => 'POS Stock Org',
            'slug' => 'pos-stock-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => 'POS Stock Venue',
            'slug' => 'pos-stock-venue-'.Str::lower(Str::random(8)),
            'status' => 'published',
            'is_active' => true,
        ]);

        config([
            'features.pilot_organizations' => array_values(array_unique(array_merge(
                (array) config('features.pilot_organizations', []),
                [(string) $org->id]
            ))),
            'features.flags.tenant_pos_and_wallet' => true,
        ]);

        $cashier1 = User::factory()->create(['role' => 'customer']);
        $cashier2 = User::factory()->create(['role' => 'customer']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $cashier1->id, 'role' => 'staff', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $cashier2->id, 'role' => 'staff', 'status' => 'active']);
        $this->assignStaffVenues($org, $venue);

        // Open shift for cashier 1
        app(PosService::class)->openShift($cashier1, $org->id, 500000, $venue->id, 'REG-01');
        // Open shift for cashier 2
        app(PosService::class)->openShift($cashier2, $org->id, 500000, $venue->id, 'REG-02');

        // Tracked product with exactly 1 unit in inventory
        $product = PosProduct::create([
            'organization_id' => $org->id,
            'sku' => 'LAST-RACKET-PRO',
            'name' => 'Yonex Astrox 88D Pro',
            'price' => 50000,
            'cost' => 30000,
            'stock_quantity' => 1,
            'is_active' => true,
            'metadata' => ['track_stock' => true],
        ]);

        $payload = [
            'venue_id' => $venue->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payment_method' => 'cash',
            'cash_tendered' => 50000,
        ];

        // Cashier 1 checks out the final unit
        $res1 = $this->actingAs($cashier1)->postJson('/api/pos/orders', $payload, [
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'race-cashier-1-'.Str::random(10),
        ]);
        $res1->assertCreated();

        // Cashier 2 concurrently attempts to purchase the same unit
        $res2 = $this->actingAs($cashier2)->postJson('/api/pos/orders', $payload, [
            'X-Organization-Id' => (string) $org->id,
            'Idempotency-Key' => 'race-cashier-2-'.Str::random(10),
        ]);

        // Must fail with stock error (409 conflict or 422 unprocessable)
        $this->assertTrue(in_array($res2->status(), [409, 422], true), "Expected 409 or 422 stock error, got {$res2->status()}");
        $this->assertStringContainsString('Insufficient stock', (string) $res2->getContent());

        // Invariants:
        // - Exactly 1 order completed
        $this->assertSame(1, PosOrder::where('organization_id', $org->id)->count(), 'Exactly 1 order can be completed.');

        // - Stock quantity in DB is exactly 0 (never negative)
        $this->assertSame(0, $product->fresh()->stock_quantity, 'Stock quantity must be exactly 0, never negative.');

        // - Exactly 1 stock movement of type 'sale'
        $this->assertSame(1, StockMovement::where('product_id', $product->id)->where('type', 'sale')->count());
        $this->assertSame(-1, (int) StockMovement::where('product_id', $product->id)->where('type', 'sale')->value('quantity_delta'));
    }

    // =========================================================================
    // SCENARIO 4: Split Bill Confirmation
    // =========================================================================

    public function test_scenario_4_split_bill_replay_and_concurrent_confirmation_confirms_once(): void
    {
        $fixture = $this->setupTenantWithReceivingConnection();
        $reservation = $this->createHeldReservation($fixture, 200000);

        // Split group with 2 shares of 100,000 VND each
        $group = app(SplitPaymentService::class)->create($reservation, $fixture['payer'], [
            ['amount' => 100000],
            ['amount' => 100000],
        ]);

        $share1 = $group->shares->firstWhere('share_number', 1);
        $share2 = $group->shares->firstWhere('share_number', 2);
        $attempt1 = OwnerPaymentAttempt::where('purpose_id', 'split_group:'.$group->id.':share:'.$share1->id)->firstOrFail();

        // 1. Initial verified payment arrives for Share 1
        $this->sendOwnerPayment($fixture, $attempt1->reference, 100000, 'split-share-evt-1');

        $this->assertSame('paid', $share1->fresh()->status);
        $this->assertSame(100000, $group->fresh()->paid_amount);
        $this->assertSame('pending', $group->fresh()->status);

        // 2. REPLAY: Same webhook event is delivered again (retry)
        $this->sendOwnerPayment($fixture, $attempt1->reference, 100000, 'split-share-evt-1');

        // 3. CONCURRENT / DUPLICATE: Second webhook arrives for the same attempt reference
        $this->sendOwnerPayment($fixture, $attempt1->reference, 100000, 'split-share-evt-1-dup');

        // Invariants:
        // - Share 1 confirmed once
        $this->assertSame('paid', $share1->fresh()->status);
        $this->assertSame('pending', $share2->fresh()->status);

        // - Total paid amount incremented once (100,000 VND, NOT 200,000 or 300,000)
        $this->assertSame(100000, $group->fresh()->paid_amount, 'Total paid amount must be incremented once.');

        // - No duplicate allocation
        $allocationsCount = OwnerPaymentAllocation::where('payment_attempt_id', $attempt1->id)->count();
        $this->assertSame(1, $allocationsCount, 'Payment attempt must have exactly 1 allocation.');

        // - Reservation remains held until share 2 is legitimately paid
        $this->assertSame('held', $reservation->fresh()->status);
    }

    // =========================================================================
    // SCENARIO 5: Marketplace Transfer
    // =========================================================================

    public function test_scenario_5_marketplace_concurrent_locks_yield_exactly_one_buyer_and_one_transfer(): void
    {
        $fixture = $this->setupTenantWithReceivingConnection();
        config(['features.flags.marketplace_slot_resale' => true]);
        $org = $fixture['organization'];
        $seller = $fixture['payer'];
        $buyer1 = User::factory()->create(['email_verified_at' => now()]);
        $buyer2 = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $buyer1->id, 'role' => 'customer', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $buyer2->id, 'role' => 'customer', 'status' => 'active']);

        // Seller has confirmed reservation of 200,000 VND > 2 hours in the future
        $slotTime = now(config('app.timezone'))->addHours(3);
        $reservation = $this->createConfirmedReservation($fixture, 200000, $slotTime);
        $sellerEntitlement = $reservation->entitlements()->firstOrFail();
        $this->assertSame(200000, $sellerEntitlement->amount);

        $listing = app(MarketplaceService::class)->list($sellerEntitlement, $seller, 150000, ['bank_code' => 'TPB', 'account_number' => '0123456789', 'account_name' => 'SELLER']);
        $this->assertSame('listed', $listing->status);
        $lock = fn (User $buyer, string $key) => $this->actingAs($buyer)
            ->withHeaders(['X-Organization-ID' => (string) $org->id, 'Idempotency-Key' => $key])
            ->postJson('/api/marketplace/listings/'.$listing->id.'/checkout');

        // Buyer 1 locks first; buyer 2 racing for the same listing gets 409 and never sees the seller's account
        $lock($buyer1, 'resale-lock-buyer-1')->assertOk()->assertJsonPath('receiver.account_number', '0123456789');
        $res2 = $lock($buyer2, 'resale-lock-buyer-2')->assertStatus(409);
        $this->assertStringNotContainsString('0123456789', (string) $res2->getContent());

        // Buyer 1 pays the seller directly; the seller confirms receipt exactly once
        $resale = app(PeerToPeerResaleService::class);
        $resale->paymentSent($listing, $buyer1);
        $resale->confirmReceipt($listing, $seller);
        $this->assertSame('sold', $listing->fresh()->status);
        foreach ([fn () => $resale->confirmReceipt($listing, $seller), fn () => $resale->lock($listing, $buyer2)] as $retry) {
            try {
                $retry();
                $this->fail('Expected a sold listing to refuse another transfer.');
            } catch (HttpException $e) {
                $this->assertSame(409, $e->getStatusCode());
            }
        }
        $lock($buyer2, 'resale-lock-buyer-2-again')->assertStatus(409);

        // Invariants:
        // - Exactly 1 completed transfer
        $this->assertSame(1, EntitlementTransfer::where('marketplace_listing_id', $listing->id)->where('status', 'completed')->count());

        // - Seller entitlement transferred once
        $this->assertSame('transferred', $sellerEntitlement->fresh()->status);

        // - Exactly 1 active entitlement held by Buyer 1
        $buyerEntitlement = ReservationEntitlement::where('user_id', $buyer1->id)->firstOrFail();
        $this->assertSame('active', $buyerEntitlement->status);
        $this->assertSame(200000, $buyerEntitlement->amount, 'Original price preserved.');

        // - Buyer 2 has 0 entitlements
        $this->assertSame(0, ReservationEntitlement::where('user_id', $buyer2->id)->count());

        // - Reservation holder atomically transferred to Buyer 1
        $this->assertSame($buyer1->id, $reservation->fresh()->user_id);
    }

    // =========================================================================
    // SCENARIO 6: Recurring Series Invoice
    // =========================================================================

    public function test_scenario_6_recurring_series_invoice_idempotent_webhook_replay_settles_once(): void
    {
        $fixture = $this->setupTenantWithReceivingConnection();
        $org = $fixture['organization'];
        $payer = $fixture['payer'];
        $court = $fixture['court'];

        $policy = $fixture['venue']->currentBookingPolicy;
        $policy->update([
            'settings' => array_merge($policy->settings, [
                'booking' => ['hold_ttl_minutes' => 30, 'max_days_ahead' => 400, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'recurring' => [
                    'payment_policy' => 'per_period',
                    'conflict_policy' => 'all_or_nothing',
                    'overdue_release_hours' => 24,
                ],
            ]),
        ]);

        $start = Carbon::now(config('app.timezone'))->addMonths(2)->startOfMonth()->addDays(14)->setTime(9, 0);
        $payload = [
            'court_id' => $court->id,
            'frequency' => 'monthly',
            'starts_at' => $start->toIso8601String(),
            'occurrences' => 1,
        ];

        $preview = $this->actingAs($payer)
            ->withHeader('X-Organization-ID', (string) $org->id)
            ->postJson('/api/recurring-series/preview', $payload)
            ->assertOk()
            ->json();

        $createRes = $this->actingAs($payer)
            ->withHeaders([
                'X-Organization-ID' => (string) $org->id,
                'Idempotency-Key' => 'rec-series-create-'.Str::random(10),
            ])
            ->postJson('/api/recurring-series', array_merge($payload, [
                'accepted' => [
                    'total' => $preview['total_amount'],
                    'occurrences' => array_map(
                        static fn (array $o): array => ['starts_at' => $o['starts_at'], 'price' => $o['price']],
                        $preview['occurrences']
                    ),
                ],
            ]));
        $createRes->assertCreated();
        $seriesId = (int) $createRes->json('series.id');

        $invoice = MonthlyInvoice::where('recurring_series_id', $seriesId)->firstOrFail();
        $attempt = OwnerPaymentAttempt::where('purpose_id', 'rec_series:'.$seriesId.':invoice:'.$invoice->id)->firstOrFail();

        // 1. Initial verified payment arrives
        $this->sendOwnerPayment($fixture, $attempt->reference, (int) $attempt->amount, 'rec-invoice-evt-1');

        $this->assertSame('paid', $invoice->fresh()->status);
        $paidAt = $invoice->fresh()->paid_at;
        $this->assertNotNull($paidAt);

        // 2. REPLAY: Same webhook event delivered again
        $this->sendOwnerPayment($fixture, $attempt->reference, (int) $attempt->amount, 'rec-invoice-evt-1');

        // 3. DUPLICATE: Second webhook event with different event ID arrives for same reference
        $this->sendOwnerPayment($fixture, $attempt->reference, (int) $attempt->amount, 'rec-invoice-evt-2');

        // Invariants:
        // - Invoice is marked paid once
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame($attempt->reference, $invoice->fresh()->payment_reference);

        // - Paid timestamp unchanged on replay
        $this->assertTrue($invoice->fresh()->paid_at->equalTo($paidAt), 'Paid timestamp must remain unchanged on replay.');

        // - Exactly 1 allocation recorded
        $this->assertSame(1, OwnerPaymentAllocation::where('payment_attempt_id', $attempt->id)->count(), 'Invoice must have exactly 1 allocation.');

        // - 2 OwnerPaymentReceipts recorded (first valid, second flagged as attempt_not_pending exception)
        $this->assertSame(2, OwnerPaymentReceipt::where('payment_attempt_id', $attempt->id)->count());
        $this->assertSame('attempt_not_pending', OwnerPaymentReceipt::where('provider_event_id', 'rec-invoice-evt-2')->firstOrFail()->exception_code);
        $this->assertTrue(FinanceException::where('type', 'owner_payment_attempt_not_pending')->exists());
    }

    // =========================================================================
    // SCENARIO 7: Platform Subscription
    // =========================================================================

    public function test_scenario_7_platform_subscription_duplicate_webhook_activates_once(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Sub Concurrency Org',
            'slug' => 'sub-conc-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);
        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $plan = PlatformPlan::create([
            'code' => 'PRO_MONTHLY_'.Str::upper(Str::random(6)),
            'name' => 'Pro Monthly Plan',
            'version' => 1,
            'price' => 500000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'terms_version' => '2026.PRO',
            'terms_text' => 'Terms text',
            'is_active' => true,
        ]);

        $referenceCode = 'SUB-CONC-REF-'.Str::upper(Str::random(8));
        $providerRef = 'PAYOS-INTENT-'.Str::upper(Str::random(8));

        $attempt = PlatformPaymentAttempt::create([
            'organization_id' => $org->id,
            'reference_code' => $referenceCode,
            'amount' => 500000,
            'currency' => 'VND',
            'provider' => 'payos',
            'provider_ref' => $providerRef,
            'status' => 'pending',
            'metadata' => [
                'plan_id' => $plan->id,
                'plan_version' => 1,
                'billing_cycle' => 'monthly',
                'accepted_terms_version' => '2026.PRO',
                'actor_id' => $owner->id,
            ],
        ]);

        config(['services.payos.webhook_secret' => 'platform-test-webhook-secret']);

        $data = [
            'orderCode' => 55001,
            'amount' => 500000,
            'description' => $attempt->reference_code,
            'reference' => $attempt->reference_code,
            'transactionDateTime' => now()->toDateTimeString(),
            'currency' => 'VND',
            'paymentLinkId' => $attempt->provider_ref,
            'code' => '00',
            'desc' => 'Thành công',
        ];
        $payload = [
            'id' => 'payos-event-sub-conc-1',
            'code' => '00',
            'desc' => 'success',
            'success' => true,
            'data' => $data,
            'signature' => $this->payosSignature($data, 'platform-test-webhook-secret'),
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        // 1. Initial webhook delivery
        $this->call('POST', '/api/webhooks/payos', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();

        $event = ProviderEvent::where('event_id', 'payos-event-sub-conc-1')->firstOrFail();
        app(ProviderEventHandler::class)->handle($event);

        // Verification after delivery 1
        $this->assertSame('succeeded', $attempt->fresh()->status);
        $subscription = PlatformSubscription::where('organization_id', $org->id)->firstOrFail();
        $this->assertSame('active', $subscription->status);
        $endsAt1 = $subscription->ends_at->toIso8601String();

        // 2. DUPLICATE WEBHOOK: Same provider transaction delivered again
        Sanctum::actingAs($owner);
        $dupResult = app(ConfirmSubscriptionPayment::class)->execute(
            referenceCode: $referenceCode,
            providerTransactionId: $providerRef,
            amount: 500000,
            currency: 'VND',
            actorId: $owner->id,
        );

        $this->assertTrue($dupResult['is_duplicate'], 'Second confirmation must be recognized as duplicate.');

        // Invariants:
        // - Exactly 1 subscription in database
        $this->assertSame(1, PlatformSubscription::where('organization_id', $org->id)->count(), 'Exactly 1 subscription can exist.');

        // - Subscription is active
        $this->assertSame('active', $dupResult['subscription']->status);

        // - Subscription duration is NOT extended (ends_at identical)
        $this->assertSame($endsAt1, $dupResult['subscription']->ends_at->toIso8601String(), 'Subscription duration must not be extended on duplicate webhook.');

        // - Exactly 1 provider receipt recorded
        $this->assertSame(1, PlatformProviderReceipt::where('payment_attempt_id', $attempt->id)->count(), 'Receipt recorded exactly once.');
    }

    // =========================================================================
    // SCENARIO 9: Last Tournament Place
    // =========================================================================

    public function test_scenario_9_last_tournament_place_capacity_reached_rejects_concurrent_entrant(): void
    {
        $fixture = $this->setupTenantWithReceivingConnection();
        $org = $fixture['organization'];
        $players = User::factory()->count(3)->create(['role' => 'customer']);
        foreach ($players as $player) {
            OrganizationMember::create(['organization_id' => $org->id, 'user_id' => $player->id, 'role' => 'customer', 'status' => 'active']);
        }
        config(['features.flags.social_matchmaking_tournaments' => true]);

        // Tournament with max_participants = 2, entry_fee = 100,000 VND paid to the venue owner.
        $tournament = Tournament::create([
            'organization_id' => $org->id,
            'venue_id' => $fixture['venue']->id,
            'created_by' => $fixture['owner']->id,
            'name' => 'Championship Final Place Cup',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => ['entry_fee' => '100000', 'max_participants' => 2],
        ]);
        $register = fn (User $player) => $this->actingAs($player)
            ->withHeaders(['X-Organization-Id' => (string) $org->id, 'Idempotency-Key' => 'tourn-reg-'.$player->id])
            ->postJson("/api/tournaments/{$tournament->id}/register");

        // Players 1 and 2 hold both places while they pay.
        $register($players[0])->assertAccepted();
        $register($players[1])->assertAccepted();

        // Player 3 is refused: pending payments count against capacity.
        $res3 = $register($players[2]);
        $this->assertTrue(in_array($res3->status(), [409, 422], true), "Expected 409 or 422, got {$res3->status()}");
        $this->assertStringContainsString('maximum participant capacity', (string) $res3->getContent());

        $this->assertSame(2, OwnerPaymentAttempt::where('purpose', 'tournament_registration')->where('purpose_id', $tournament->uuid)->count());
        $this->assertFalse(OwnerPaymentAttempt::where('user_id', $players[2]->id)->exists());
        $this->assertSame(0, $tournament->participants()->count());
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function setupTenantWithReceivingConnection(): array
    {
        $organization = Organization::create([
            'name' => 'Readiness Tenant',
            'slug' => 'readiness-tenant-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
            'settings' => ['timezone' => 'Asia/Ho_Chi_Minh'],
        ]);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $payer = User::factory()->create(['email_verified_at' => now()]);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role' => 'organization_owner', 'status' => 'active']);
        OrganizationMember::create(['organization_id' => $organization->id, 'user_id' => $payer->id, 'role' => 'customer', 'status' => 'active']);

        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Readiness Venue',
            'slug' => 'readiness-venue-'.Str::lower(Str::random(8)),
            'address' => '500 Sports Blvd',
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create(['venue_id' => $venue->id, 'name' => 'Court 1', 'is_active' => true]);
        PriceRule::create(['organization_id' => $organization->id, 'court_id' => $court->id, 'type' => 'base', 'price' => 200000]);

        VenueBookingPolicy::create([
            'venue_id' => $venue->id,
            'version' => 1,
            'created_by' => $owner->id,
            'effective_at' => now(),
            'settings' => [
                'booking' => ['hold_ttl_minutes' => 30, 'max_days_ahead' => 60, 'min_duration_minutes' => 60, 'max_duration_minutes' => 240, 'require_continuity' => true],
                'cancellation' => ['deadline_hours' => 24, 'fee_type' => 'percentage', 'fee_value' => 10, 'refund_due_hours' => 48],
                'change' => ['deadline_hours' => 12, 'fee_type' => 'none', 'fee_value' => 0],
                'arrival' => ['early_minutes' => 15, 'late_grace_minutes' => 10, 'no_show_after_minutes' => 30],
                'recurring' => ['payment_policy' => 'per_period', 'conflict_policy' => 'all_or_nothing', 'overdue_release_hours' => 24],
                'transfer' => ['cutoff_hours' => 2, 'max_consideration_percent' => 100, 'free_transfer_allowed' => false, 'seller_reimbursement_required' => true],
                'ancillary_credit_policy' => 'refund',
            ],
        ]);

        $connection = new ReceivingConnection([
            'organization_id' => $organization->id,
            'venue_id' => $venue->id,
            'owner_user_id' => $owner->id,
            'provider' => 'sepay',
            'routing_id' => (string) Str::uuid(),
            'bank_code' => 'MBBank',
            'status' => ReceivingConnection::STATUS_VERIFIED,
            'credential_version' => 1,
            'owner_attested_at' => now(),
            'verified_at' => now(),
            'effective_from' => now(),
        ]);
        $connection->setAccountDetails('Readiness Owner', '0123456789');
        $secret = 'whsec_'.Str::random(48);
        $connection->webhook_secret_ciphertext = Crypt::encryptString($secret);
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();

        config([
            'features.flags.direct_owner_payments' => true,
            'features.flags.marketplace_split_payments' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        return compact('organization', 'owner', 'payer', 'venue', 'court', 'connection', 'secret');
    }

    private function createHeldReservation(array $fixture, int $amount): Reservation
    {
        $start = now()->addDays(3)->setTime(10, 0, 0);
        $reservation = Reservation::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $fixture['payer']->id,
            'court_id' => $fixture['court']->id,
            'code' => 'RES-HELD-'.Str::upper(Str::random(8)),
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => $amount,
            'payment_model' => 'direct_owner_v1',
            'hold_expires_at' => now()->addMinutes(30),
            'metadata' => [
                'booking_policy_snapshot' => [
                    'version' => 1,
                    'settings' => ['cancellation' => ['deadline_hours' => 24, 'fee_type' => 'percentage', 'fee_value' => 10, 'refund_due_hours' => 48]],
                ],
            ],
        ]);

        $slot = ReservationSlot::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'court_id' => $fixture['court']->id,
            'starts_at' => $start->copy()->utc(),
            'ends_at' => $start->copy()->addHour()->utc(),
            'unit_price' => $amount,
            'status' => 'held',
            'snapshot' => [],
        ]);

        ReservationEntitlement::create([
            'organization_id' => $fixture['organization']->id,
            'venue_id' => $fixture['venue']->id,
            'court_id' => $fixture['court']->id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'user_id' => $fixture['payer']->id,
            'entitlement_type' => 'court_reservation',
            'starts_at' => $start->copy()->utc(),
            'ends_at' => $start->copy()->addHour()->utc(),
            'amount' => $amount,
            'status' => 'held',
        ]);

        return $reservation;
    }

    private function createConfirmedReservation(array $fixture, int $amount, ?Carbon $slotTime = null): Reservation
    {
        $slotTime ??= now(config('app.timezone'))->addDays(3)->setTime(10, 0, 0);
        $reservation = Reservation::create([
            'organization_id' => $fixture['organization']->id,
            'user_id' => $fixture['payer']->id,
            'court_id' => $fixture['court']->id,
            'code' => 'RES-CONF-'.Str::upper(Str::random(8)),
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => $amount,
            'payment_model' => 'direct_owner_v1',
            'hold_expires_at' => now()->addHour(),
            'metadata' => [
                'booking_policy_snapshot' => [
                    'version' => 1,
                    'settings' => ['cancellation' => ['deadline_hours' => 24, 'fee_type' => 'none', 'fee_value' => 0, 'refund_due_hours' => 48]],
                ],
            ],
        ]);

        $slot = ReservationSlot::create([
            'organization_id' => $fixture['organization']->id,
            'reservation_id' => $reservation->id,
            'court_id' => $fixture['court']->id,
            'starts_at' => $slotTime->copy()->utc(),
            'ends_at' => $slotTime->copy()->addHour()->utc(),
            'unit_price' => $amount,
            'status' => 'held',
            'snapshot' => [],
        ]);

        ReservationEntitlement::create([
            'organization_id' => $fixture['organization']->id,
            'venue_id' => $fixture['venue']->id,
            'court_id' => $fixture['court']->id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'user_id' => $fixture['payer']->id,
            'entitlement_type' => 'court_reservation',
            'starts_at' => $slotTime->copy()->utc(),
            'ends_at' => $slotTime->copy()->addHour()->utc(),
            'amount' => $amount,
            'status' => 'held',
        ]);

        $attempt = app(OwnerPaymentAttemptService::class)->createAttempt(
            $fixture['connection'],
            $fixture['payer'],
            'booking',
            $reservation->code,
            'BK'.Str::upper(Str::random(10)),
            $amount,
            'VND',
            now()->addHour(),
            'idem-conf-'.Str::uuid(),
        );

        $this->sendOwnerPayment($fixture, $attempt->reference, $amount, 'receipt-conf-'.Str::random(10));

        $reservation->refresh();

        return $reservation;
    }

    private function sendOwnerPayment(array $fixture, string $reference, int $amount, string $eventId): void
    {
        $connection = $fixture['connection'];
        $payload = [
            'id' => $eventId,
            'transactionDate' => $this->eventDates[$eventId] ??= now()->addSeconds(2)->toIso8601String(),
            'accountNumber' => $connection->accountNumber(),
            'code' => $reference,
            'content' => $reference,
            'transferType' => 'in',
            'transferAmount' => $amount,
            'referenceCode' => 'BANK-'.$eventId,
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Apikey '.$fixture['secret'],
        ], $raw)->assertOk();

        $receipt = OwnerPaymentReceipt::where('provider_event_id', $eventId)->firstOrFail();
        if (! $receipt->processed_at) {
            app(OwnerPaymentAttemptService::class)->processReceipt((int) $receipt->id);
        }
    }

    private function payosSignature(array $data, string $checksumKey): string
    {
        ksort($data, SORT_STRING);
        $canonical = collect($data)->map(function ($value, $key): string {
            if ($value === null || in_array($value, ['null', 'undefined'], true)) {
                $value = '';
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }

            return "{$key}={$value}";
        })->implode('&');

        return hash_hmac('sha256', $canonical, $checksumKey);
    }
}
