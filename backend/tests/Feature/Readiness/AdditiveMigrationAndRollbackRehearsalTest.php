<?php

namespace Tests\Feature\Readiness;

use App\Models\Booking;
use App\Models\BookingSlot;
use App\Models\Court;
use App\Models\CourtPriceSlot;
use App\Models\Journal;
use App\Models\LegacyBookingMapping;
use App\Models\LegacyBookingSlotMapping;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerRefundObligation;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\ProviderEvent;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Finance\LedgerService;
use App\Services\Finance\ReconciliationService;
use App\Services\Payment\WalletPaymentService;
use App\Services\Reservation\LegacyBookingBackfillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Task 13.4: Rehearse additive migration/rollback on a replica dataset:
 * - Count / Checksum / Totals / Legacy response preservation
 * - No policy or balance rewrite (0 new commission does not rewrite historical journals)
 * - Preserve ingestion (SePay webhook) & reconciliation when new booking commands are paused
 * - Additive rollback verification ensuring underlying source records are 100% intact
 */
class AdditiveMigrationAndRollbackRehearsalTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantEnvironment(string $prefix = 'rehearsal'): array
    {
        $org = Organization::create([
            'name' => "Org {$prefix}",
            'slug' => "org-{$prefix}-".uniqid(),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $venue = Venue::create([
            'organization_id' => $org->id,
            'name' => "Venue {$prefix}",
            'slug' => "venue-{$prefix}-".uniqid(),
            'address' => '100 Nguyen Hue, D1, HCMC',
            'phone' => '0901234567',
            'status' => 'published',
            'is_active' => true,
        ]);

        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => "Court {$prefix} 1",
            'is_active' => true,
        ]);

        $customer = User::factory()->create([
            'name' => "Customer {$prefix}",
            'email' => "customer-{$prefix}-".uniqid().'@aura.test',
        ]);
        $customer->markEmailAsVerified();

        OrganizationMember::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'role' => 'customer',
            'status' => 'active',
        ]);

        return [$org, $venue, $court, $customer];
    }

    private function createLegacyBookingWithSlotsAndPayments(
        Court $court,
        User $user,
        array $params = []
    ): Booking {
        $code = $params['code'] ?? 'BK-LEG-'.strtoupper(uniqid());
        $status = $params['status'] ?? 'confirmed';
        $totalAmount = $params['total_amount'] ?? 200000;
        $slotCount = $params['slot_count'] ?? 2;
        $unitPrice = (int) ($totalAmount / max(1, $slotCount));
        $startHour = $params['start_hour'] ?? 8;
        $dayOffset = $params['day_offset'] ?? 2;

        $booking = Booking::create([
            'code' => $code,
            'user_id' => $user->id,
            'court_id' => $court->id,
            'total_amount' => $totalAmount,
            'status' => $status,
            'payment_model' => $params['payment_model'] ?? 'legacy_wallet_v1',
            'payment_expires_at' => $params['payment_expires_at'] ?? now()->addMinutes(30),
            'policy_snapshot' => $params['policy_snapshot'] ?? [
                'venue_booking_policy' => ['cancellation_window_hours' => 24, 'refund_percentage' => 100],
            ],
        ]);

        $baseTime = now()->addDays($dayOffset)->setHour($startHour)->setMinute(0)->setSecond(0);
        for ($i = 0; $i < $slotCount; $i++) {
            $slotStart = (clone $baseTime)->addHours($i);
            $slotEnd = (clone $slotStart)->addHour();

            BookingSlot::create([
                'booking_id' => $booking->id,
                'court_id' => $court->id,
                'starts_at' => $slotStart,
                'ends_at' => $slotEnd,
                'unit_price' => $unitPrice,
                'status' => $status === 'pending_payment' ? 'held' : $status,
            ]);
        }

        if (! empty($params['create_payment'])) {
            Payment::create([
                'booking_id' => $booking->id,
                'provider' => 'vietqr',
                'external_transaction_id' => 'TX-MIG-'.uniqid(),
                'amount' => $totalAmount,
                'transfer_code' => "ORD {$booking->code}",
                'status' => 'paid',
                'received_at' => now(),
            ]);
        }

        return $booking;
    }

    private function computeDatasetChecksum(): string
    {
        $bookings = Booking::with(['slots', 'payments'])->orderBy('id')->get();
        $payload = $bookings->map(function (Booking $b) {
            return [
                'id' => $b->id,
                'code' => $b->code,
                'user_id' => $b->user_id,
                'court_id' => $b->court_id,
                'total_amount' => (int) $b->total_amount,
                'status' => $b->status,
                'payment_model' => (string) $b->payment_model,
                'slots' => $b->slots->sortBy('id')->map(fn (BookingSlot $s) => [
                    'id' => $s->id,
                    'court_id' => $s->court_id,
                    'starts_at' => (string) $s->starts_at,
                    'ends_at' => (string) $s->ends_at,
                    'unit_price' => (int) $s->unit_price,
                    'status' => $s->status,
                ])->values()->all(),
                'payments' => $b->payments->sortBy('id')->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'provider' => $p->provider,
                    'external_transaction_id' => $p->external_transaction_id,
                    'amount' => (int) $p->amount,
                    'status' => $p->status,
                ])->values()->all(),
            ];
        })->all();

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function snapshotLegacyDataset(): array
    {
        return [
            'bookings' => Booking::orderBy('id')->get()->map->getAttributes()->all(),
            'slots' => BookingSlot::orderBy('id')->get()->map->getAttributes()->all(),
            'payments' => Payment::orderBy('id')->get()->map->getAttributes()->all(),
        ];
    }

    /**
     * Scenario 1: Additive backfill rehearsal & Checksums.
     * - Calculate baseline counts, total sums, and checksums for V1 bookings, slots, payments.
     * - Run additive migration backfill (linking to Organization, Venue, and LegacyBookingMapping).
     * - Invariants: Pre-migration counts, total amounts, and checksums are 100% preserved. No legacy rows mutated destructively.
     */
    public function test_01_additive_backfill_rehearsal_preserves_counts_totals_and_checksums_without_destructive_mutation(): void
    {
        [$org, $venue, $court, $customer] = $this->createTenantEnvironment('sc1');

        // Create representative diverse V1 bookings with non-overlapping slot hours
        $booking1 = $this->createLegacyBookingWithSlotsAndPayments($court, $customer, [
            'code' => 'BK-V1-CONFIRMED',
            'status' => 'confirmed',
            'total_amount' => 200000,
            'slot_count' => 2,
            'start_hour' => 8,
            'create_payment' => true,
        ]);

        $booking2 = $this->createLegacyBookingWithSlotsAndPayments($court, $customer, [
            'code' => 'BK-V1-PENDING',
            'status' => 'pending_payment',
            'total_amount' => 150000,
            'slot_count' => 1,
            'start_hour' => 10,
            'create_payment' => false,
        ]);

        $booking3 = $this->createLegacyBookingWithSlotsAndPayments($court, $customer, [
            'code' => 'BK-V1-COMPLETED',
            'status' => 'confirmed',
            'total_amount' => 300000,
            'slot_count' => 3,
            'start_hour' => 12,
            'create_payment' => true,
        ]);

        // Baseline counts and sums
        $baselineBookingCount = Booking::count();
        $baselineSlotCount = BookingSlot::count();
        $baselinePaymentCount = Payment::count();

        $baselineBookingSum = (int) Booking::sum('total_amount');
        $baselineSlotSum = (int) BookingSlot::sum('unit_price');
        $baselinePaymentSum = (int) Payment::sum('amount');

        $this->assertSame(3, $baselineBookingCount);
        $this->assertSame(6, $baselineSlotCount);
        $this->assertSame(2, $baselinePaymentCount);
        $this->assertSame(650000, $baselineBookingSum);
        $this->assertSame(650000, $baselineSlotSum);
        $this->assertSame(500000, $baselinePaymentSum);

        // Baseline cryptographic SHA-256 checksum and attribute snapshot
        $baselineChecksum = $this->computeDatasetChecksum();
        $baselineSnapshot = $this->snapshotLegacyDataset();
        $this->assertNotEmpty($baselineChecksum);

        // Assert no canonical reservations or mappings exist prior to backfill
        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, ReservationSlot::count());
        $this->assertSame(0, ReservationEntitlement::count());
        $this->assertSame(0, LegacyBookingMapping::count());
        $this->assertSame(0, LegacyBookingSlotMapping::count());

        // Run additive migration backfill via service
        $backfillService = app(LegacyBookingBackfillService::class);
        $auditResult = $backfillService->audit($org->id);
        $this->assertTrue($auditResult['dry_run']);
        $this->assertSame(3, $auditResult['pending_count']);
        $this->assertSame(0, $auditResult['orphan_count']);

        $migrationResult = $backfillService->backfill($org->id);
        $this->assertSame(3, $migrationResult['created']);

        // Verify additive canonical mappings were established
        $this->assertSame(3, LegacyBookingMapping::where('organization_id', $org->id)->count());
        $this->assertSame(6, LegacyBookingSlotMapping::where('organization_id', $org->id)->count());
        $this->assertSame(3, Reservation::where('organization_id', $org->id)->count());
        $this->assertSame(6, ReservationSlot::where('organization_id', $org->id)->count());
        $this->assertSame(6, ReservationEntitlement::where('organization_id', $org->id)->count());

        // Verify individual booking mappings
        foreach ([$booking1, $booking2, $booking3] as $legacyBooking) {
            $mapping = LegacyBookingMapping::where('legacy_booking_id', $legacyBooking->id)->first();
            $this->assertNotNull($mapping);
            $this->assertSame($legacyBooking->code, $mapping->legacy_code);
            $this->assertSame($legacyBooking->status, $mapping->legacy_status);
            $this->assertSame((int) $legacyBooking->total_amount, $mapping->legacy_total_amount);
            $this->assertSame($legacyBooking->slots()->count(), $mapping->legacy_slot_count);
            $this->assertNotEmpty($mapping->source_checksum);

            $reservation = Reservation::find($mapping->reservation_id);
            $this->assertNotNull($reservation);
            $this->assertSame("LEGACY-{$legacyBooking->code}", $reservation->code);
            $this->assertSame($org->id, $reservation->organization_id);
            $this->assertSame((int) $legacyBooking->total_amount, $reservation->total_amount);
        }

        // Invariants verification: 100% preservation of counts, totals, and checksums
        $this->assertSame($baselineBookingCount, Booking::count(), 'Booking count must remain 100% preserved');
        $this->assertSame($baselineSlotCount, BookingSlot::count(), 'Slot count must remain 100% preserved');
        $this->assertSame($baselinePaymentCount, Payment::count(), 'Payment count must remain 100% preserved');

        $this->assertSame($baselineBookingSum, (int) Booking::sum('total_amount'), 'Booking total must remain 100% preserved');
        $this->assertSame($baselineSlotSum, (int) BookingSlot::sum('unit_price'), 'Slot sum must remain 100% preserved');
        $this->assertSame($baselinePaymentSum, (int) Payment::sum('amount'), 'Payment sum must remain 100% preserved');

        $postMigrationChecksum = $this->computeDatasetChecksum();
        $this->assertSame($baselineChecksum, $postMigrationChecksum, 'Cryptographic checksum must match baseline identically');

        // Confirm zero destructive mutations on underlying legacy rows
        $postSnapshot = $this->snapshotLegacyDataset();
        $this->assertEquals($baselineSnapshot, $postSnapshot, 'Attributes of legacy rows must not be mutated destructively');
    }

    /**
     * Scenario 2: Legacy history equivalence after migration.
     * - Historical bookings are read through their mapped reservation (GET /api/reservations/{code}).
     * - Status, pricing, slots and court details match the source booking, and re-running the backfill serves an identical response.
     */
    public function test_02_legacy_history_read_through_mapped_reservation_matches_source_booking(): void
    {
        [$org, $venue, $court, $customer] = $this->createTenantEnvironment('sc2');

        $booking = $this->createLegacyBookingWithSlotsAndPayments($court, $customer, [
            'code' => 'BK-EQUIV-2026',
            'status' => 'confirmed',
            'total_amount' => 240000,
            'slot_count' => 2,
            'start_hour' => 8,
            'create_payment' => true,
        ]);

        // Execute additive migration backfill
        $backfillService = app(LegacyBookingBackfillService::class);
        $backfillService->backfill($org->id);

        $this->assertDatabaseHas('legacy_booking_mappings', [
            'legacy_booking_id' => $booking->id,
            'organization_id' => $org->id,
        ]);

        $responseBefore = $this->actingAs($customer)->getJson('/api/reservations/LEGACY-'.$booking->code);
        $responseBefore->assertOk();

        $payloadBefore = $responseBefore->json('reservation');
        $this->assertSame('BK-EQUIV-2026', $payloadBefore['legacy_mapping'][0]['legacy_code']);
        $this->assertSame('confirmed', $payloadBefore['legacy_mapping'][0]['legacy_status']);
        $this->assertSame('confirmed', $payloadBefore['status']);
        $this->assertSame(240000, $payloadBefore['total_amount']);
        $this->assertSame($court->id, $payloadBefore['court']['id']);
        $this->assertSame($venue->id, $payloadBefore['court']['venue']['id']);
        $this->assertCount(2, $payloadBefore['slots']);
        foreach ($booking->slots()->orderBy('starts_at')->get() as $i => $slot) {
            $this->assertSame($slot->unit_price, $payloadBefore['slots'][$i]['unit_price']);
            $this->assertTrue($slot->starts_at->equalTo(Carbon::parse($payloadBefore['slots'][$i]['starts_at'])));
        }
        // The payment stays on the historical booking row.
        $this->assertSame(1, Payment::where('booking_id', $booking->id)->count());

        // Re-running the backfill verifies the mapping checksum and changes nothing.
        $backfillService->backfill($org->id);
        $this->actingAs($customer)->getJson('/api/reservations/LEGACY-'.$booking->code)
            ->assertOk()
            ->assertExactJson($responseBefore->json());
    }

    /**
     * Scenario 3: No policy or balance rewrite.
     * - Historical transactions, journals, and wallet balances maintain original commission and rates (0 new commission does NOT rewrite historical commission journals).
     * - Historical refunds retain original amounts and policy snapshots.
     */
    public function test_03_no_policy_or_balance_rewrite_preserves_historical_commission_and_refund_snapshots(): void
    {
        [$org, $venue, $court, $customer] = $this->createTenantEnvironment('sc3');

        $ledger = app(LedgerService::class);
        $walletService = app(WalletPaymentService::class);

        // 1. Initial wallet setup with balance
        $wallet = $walletService->getOrCreateWallet($customer->id, $org->id);
        $walletService->credit($wallet, 1000000, 'initial_deposit', 1, 'Initial rehearsal balance');
        $initialBalance = (int) $wallet->fresh()->balance;
        $this->assertSame(1000000, $initialBalance);

        // 2. Historical booking payment with 10,000 VND platform commission (5% rate)
        $booking = $this->createLegacyBookingWithSlotsAndPayments($court, $customer, [
            'code' => 'BK-HIST-COMMISSION',
            'status' => 'confirmed',
            'total_amount' => 200000,
            'slot_count' => 1,
            'start_hour' => 8,
            'create_payment' => true,
        ]);

        $accounts = $ledger->systemAccounts($org->id);
        $historicalJournal = $ledger->post([
            'organization_id' => $org->id,
            'event_type' => 'booking.wallet_payment',
            'event_id' => "booking:{$booking->id}:payment:historical",
            'currency' => 'VND',
            'posted_at' => now()->subDays(10),
            'metadata' => [
                'commission_bps' => 500,
                'commission_amount' => 10000,
                'policy_snapshot' => ['commission_rate' => 0.05],
            ],
        ], [
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'debit', 'amount' => 200000],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => 190000],
            ['account_id' => $accounts['platform_commission']->id, 'side' => 'credit', 'amount' => 10000],
        ]);

        $this->assertSame(200000, $historicalJournal->total_debit);
        $this->assertSame(200000, $historicalJournal->total_credit);

        // 3. Historical refund with explicit original policy snapshot
        $historicalRefund = Refund::create([
            'booking_id' => $booking->id,
            'amount' => 75000,
            'status' => 'completed',
            'note' => 'Historical 75% refund tier snapshot',
            'processed_at' => now()->subDays(9),
        ]);

        $historicalObligation = OwnerRefundObligation::create([
            'organization_id' => $org->id,
            'venue_id' => $venue->id,
            'payer_id' => $customer->id,
            'reference_code' => 'REF-HIST-'.uniqid(),
            'idempotency_key' => 'IDEM-REF-HIST-'.uniqid(),
            'request_fingerprint' => hash('sha256', uniqid()),
            'slot_ids' => [1],
            'gross_amount' => 75000,
            'fee_amount' => 0,
            'amount' => 75000,
            'currency' => 'VND',
            'status' => 'approved',
            'reason' => 'Historical tier refund',
            'policy_snapshot' => [
                'policy_version' => 1,
                'penalty_bps' => 2500,
                'original_commission_rate' => 0.05,
                'snapshot_time' => '2026-08-01T12:00:00Z',
            ],
            'recipient_ciphertext' => Crypt::encryptString(json_encode(['name' => $customer->name])),
            'due_at' => now()->addDays(2),
            'requested_at' => now()->subDays(9),
            'approved_at' => now()->subDays(8),
            'requested_by' => $customer->id,
        ]);

        // 4. Cutover execution: Set 0 new commission rate globally and run migration backfill
        config(['finance.commission_bps' => 0]);

        $backfillService = app(LegacyBookingBackfillService::class);
        $backfillService->backfill($org->id);

        // 5. Invariants Verification:
        // A) Historical journal MUST retain original platform commission entry and amounts
        $reloadedJournal = Journal::where('organization_id', $org->id)
            ->where('event_id', "booking:{$booking->id}:payment:historical")
            ->firstOrFail();

        $this->assertSame(200000, $reloadedJournal->total_debit);
        $this->assertSame(200000, $reloadedJournal->total_credit);

        $commissionEntry = $reloadedJournal->entries()
            ->where('account_id', $accounts['platform_commission']->id)
            ->first();
        $this->assertNotNull($commissionEntry, 'Platform commission line must not be dropped');
        $this->assertSame('credit', $commissionEntry->side);
        $this->assertSame(10000, $commissionEntry->amount, 'Platform commission line must not be zeroed out or rewritten');

        $tenantPayableEntry = $reloadedJournal->entries()
            ->where('account_id', $accounts['tenant_payable']->id)
            ->first();
        $this->assertSame(190000, $tenantPayableEntry->amount);

        // B) Wallet balance maintains original balance (no unauthorized adjustment or rewrite)
        $this->assertSame($initialBalance, (int) $wallet->fresh()->balance);

        // C) Historical refund maintains original amount and status
        $freshRefund = $historicalRefund->fresh();
        $this->assertSame(75000, $freshRefund->amount);
        $this->assertSame('completed', $freshRefund->status);
        $this->assertSame('Historical 75% refund tier snapshot', $freshRefund->note);

        // D) Historical obligation maintains exact policy snapshot
        $freshObligation = $historicalObligation->fresh();
        $this->assertSame(75000, $freshObligation->amount);
        $this->assertSame('approved', $freshObligation->status);
        $this->assertSame([
            'policy_version' => 1,
            'penalty_bps' => 2500,
            'original_commission_rate' => 0.05,
            'snapshot_time' => '2026-08-01T12:00:00Z',
        ], $freshObligation->policy_snapshot);

        // E) New transaction under zero commission does NOT pollute historical journal
        $newBooking = $this->createLegacyBookingWithSlotsAndPayments($court, $customer, [
            'code' => 'BK-NEW-ZERO-COMM',
            'status' => 'confirmed',
            'total_amount' => 100000,
            'slot_count' => 1,
            'start_hour' => 14,
            'create_payment' => true,
        ]);

        $newJournal = $ledger->post([
            'organization_id' => $org->id,
            'event_type' => 'booking.wallet_payment',
            'event_id' => "booking:{$newBooking->id}:payment:new",
            'currency' => 'VND',
            'posted_at' => now(),
        ], [
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'debit', 'amount' => 100000],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => 100000],
        ]);

        $this->assertSame(100000, $newJournal->total_debit);
        $this->assertNull($newJournal->entries()->where('account_id', $accounts['platform_commission']->id)->first());

        // Historical journal remains completely unaffected
        $this->assertSame(10000, $reloadedJournal->fresh()->entries()->where('account_id', $accounts['platform_commission']->id)->first()->amount);
    }

    /**
     * Scenario 4: Pause new commands while preserving ingestion & reconciliation.
     * - When new booking creation is paused/blocked (e.g. maintenance or cutover flag),
     *   incoming provider webhook ingestion (POST /api/v1/webhooks/sepay) and
     *   reconciliation runs (ReconciliationService::run) continue operating normally without dropping receipts.
     */
    public function test_04_pause_new_commands_while_preserving_provider_webhook_ingestion_and_reconciliation(): void
    {
        config(['services.sepay.hmac_secret' => 'sepay-rehearsal-secret']);

        [$org, $venue, $court, $customer] = $this->createTenantEnvironment('sc4');

        $tomorrow = Carbon::now(config('app.timezone'))->addDay()->setTime(10, 0)->startOfHour();
        CourtPriceSlot::create([
            'court_id' => $court->id,
            'weekday' => $tomorrow->dayOfWeekIso,
            'starts_at' => '10:00',
            'ends_at' => '11:00',
            'price' => 150000,
            'is_active' => true,
        ]);

        // 1. Pending topup PaymentIntent prepared prior to pause
        $intent = PaymentIntent::create([
            'organization_id' => $org->id,
            'user_id' => $customer->id,
            'reference' => 'DEP-PAUSE-001',
            'idempotency_key' => 'IDEM-DEP-'.uniqid(),
            'amount' => 250000,
            'currency' => 'VND',
            'provider' => 'sepay',
            'status' => 'pending',
            'metadata' => ['type' => 'wallet_deposit'],
        ]);

        // 2. Cutover simulation: Pause/block new booking commands via court maintenance
        $court->update(['is_active' => false]);

        $initialBookingCount = Booking::count();

        // Customer attempts to create new booking while command is paused -> rejected
        $blockedBookingResponse = $this->actingAs($customer)->withHeader('Idempotency-Key', 'paused-checkout')
            ->postJson('/api/reservations/checkout', [
                'court_id' => $court->id,
                'slots' => [$tomorrow->toIso8601String()],
            ]);
        $blockedBookingResponse->assertStatus(404)
            ->assertJsonPath('message', 'Sân không tồn tại hoặc đã ngừng hoạt động.');

        // Verify database has no new bookings
        $this->assertSame($initialBookingCount, Booking::count());
        $this->assertSame(0, Reservation::count());

        // 3. Ingestion Check: While new booking commands are blocked, provider webhook ingestion
        // at POST /api/v1/webhooks/sepay MUST succeed and process receipts without dropping
        $webhookPayload = [
            'id' => 'tx-sepay-ingress-100',
            'code' => "ORD NAP {$customer->id} {$intent->reference}",
            'transferAmount' => 250000,
            'transferType' => 'in',
        ];
        $rawContent = json_encode($webhookPayload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawContent, 'sepay-rehearsal-secret');

        $webhookResponse = $this->call('POST', '/api/v1/webhooks/sepay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_SIGNATURE' => $signature,
        ], $rawContent);

        $webhookResponse->assertOk()->assertJson(['success' => true]);

        // Verify receipt was successfully processed and credited
        $this->assertSame('succeeded', $intent->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'external_transaction_id' => 'tx-sepay-ingress-100',
            'amount' => 250000,
            'status' => 'paid',
        ]);
        $wallet = app(WalletPaymentService::class)->getOrCreateWallet($customer->id, $org->id);
        $this->assertSame(250000, (int) $wallet->fresh()->balance);

        // Record the verified inbound provider event for reconciliation
        ProviderEvent::create([
            'organization_id' => $org->id,
            'provider' => 'sepay',
            'event_id' => 'tx-sepay-ingress-100',
            'type' => 'payment.status',
            'status' => 'PAID',
            'amount' => 250000,
            'payload' => $webhookPayload,
            'verified_at' => now(),
        ]);

        // 4. Reconciliation Check: Run ReconciliationService while commands are paused
        $reconciliationService = app(ReconciliationService::class);
        $reconRun = $reconciliationService->run($org);

        $this->assertNotNull($reconRun);
        $this->assertSame('matched', $reconRun->status);
        $this->assertSame(0, (int) $reconRun->mismatch_amount);
        $this->assertNotNull($reconRun->completed_at);
        $this->assertEmpty($reconRun->details['mismatches'] ?? []);
    }

    /**
     * Scenario 5: Additive Rollback verification.
     * - Simulate rollback of additive mappings/records.
     * - Verify that underlying source booking and payment records remain intact and unchanged.
     */
    public function test_05_additive_rollback_safely_removes_mappings_and_leaves_source_records_intact(): void
    {
        [$org, $venue, $court, $customer] = $this->createTenantEnvironment('sc5');

        // Create initial legacy dataset with non-overlapping slot hours
        $bookingA = $this->createLegacyBookingWithSlotsAndPayments($court, $customer, [
            'code' => 'BK-ROLLBACK-A',
            'status' => 'confirmed',
            'total_amount' => 180000,
            'slot_count' => 2,
            'start_hour' => 8,
            'create_payment' => true,
        ]);

        $bookingB = $this->createLegacyBookingWithSlotsAndPayments($court, $customer, [
            'code' => 'BK-ROLLBACK-B',
            'status' => 'pending_payment',
            'total_amount' => 120000,
            'slot_count' => 1,
            'start_hour' => 11,
            'create_payment' => false,
        ]);

        // Baseline pre-migration calculations
        $baselineBookingCount = Booking::count();
        $baselineSlotCount = BookingSlot::count();
        $baselinePaymentCount = Payment::count();

        $baselineBookingTotal = (int) Booking::sum('total_amount');
        $baselineSlotTotal = (int) BookingSlot::sum('unit_price');
        $baselinePaymentTotal = (int) Payment::sum('amount');

        $baselineChecksum = $this->computeDatasetChecksum();
        $baselineSnapshot = $this->snapshotLegacyDataset();

        $this->assertSame(2, $baselineBookingCount);
        $this->assertSame(3, $baselineSlotCount);
        $this->assertSame(1, $baselinePaymentCount);
        $this->assertSame(300000, $baselineBookingTotal);

        // 1. Run additive migration backfill
        $backfillService = app(LegacyBookingBackfillService::class);
        $migrationResult = $backfillService->backfill($org->id);
        $this->assertSame(2, $migrationResult['created']);

        // Assert additive structures exist
        $this->assertSame(2, LegacyBookingMapping::where('organization_id', $org->id)->count());
        $this->assertSame(3, LegacyBookingSlotMapping::where('organization_id', $org->id)->count());
        $this->assertSame(2, Reservation::where('organization_id', $org->id)->count());
        $this->assertSame(3, ReservationSlot::where('organization_id', $org->id)->count());
        $this->assertSame(3, ReservationEntitlement::where('organization_id', $org->id)->count());

        // 2. Simulate rollback of additive mappings and generated canonical entities
        LegacyBookingSlotMapping::where('organization_id', $org->id)->delete();
        LegacyBookingMapping::where('organization_id', $org->id)->delete();
        ReservationEntitlement::where('organization_id', $org->id)->delete();
        ReservationSlot::where('organization_id', $org->id)->delete();
        Reservation::where('organization_id', $org->id)->delete();

        // 3. Verify additive rollback outcome:
        // A) Additive mappings and canonical reservations are completely removed
        $this->assertSame(0, LegacyBookingMapping::where('organization_id', $org->id)->count());
        $this->assertSame(0, LegacyBookingSlotMapping::where('organization_id', $org->id)->count());
        $this->assertSame(0, Reservation::where('organization_id', $org->id)->count());
        $this->assertSame(0, ReservationSlot::where('organization_id', $org->id)->count());
        $this->assertSame(0, ReservationEntitlement::where('organization_id', $org->id)->count());

        // B) Underlying legacy source records are 100% intact and unchanged
        $this->assertSame($baselineBookingCount, Booking::count(), 'Booking count must match baseline after rollback');
        $this->assertSame($baselineSlotCount, BookingSlot::count(), 'Slot count must match baseline after rollback');
        $this->assertSame($baselinePaymentCount, Payment::count(), 'Payment count must match baseline after rollback');

        $this->assertSame($baselineBookingTotal, (int) Booking::sum('total_amount'), 'Booking total must match baseline after rollback');
        $this->assertSame($baselineSlotTotal, (int) BookingSlot::sum('unit_price'), 'Slot total must match baseline after rollback');
        $this->assertSame($baselinePaymentTotal, (int) Payment::sum('amount'), 'Payment total must match baseline after rollback');

        // C) Cryptographic checksum is 100% identical post-rollback
        $postRollbackChecksum = $this->computeDatasetChecksum();
        $this->assertSame($baselineChecksum, $postRollbackChecksum, 'Checksum after rollback must be identical to pre-migration baseline');

        // D) Attribute snapshot of all legacy records is unchanged
        $postRollbackSnapshot = $this->snapshotLegacyDataset();
        $this->assertEquals($baselineSnapshot, $postRollbackSnapshot, 'Attributes of source records must remain identical after rollback');

        // 4. Re-run backfill after rollback to ensure repeatable, idempotent behavior
        $auditAfterRollback = $backfillService->audit($org->id);
        $this->assertSame(2, $auditAfterRollback['pending_count']);
        $this->assertSame(0, $auditAfterRollback['mapped_count']);

        $reBackfillResult = $backfillService->backfill($org->id);
        $this->assertSame(2, $reBackfillResult['created']);
        $this->assertSame(2, LegacyBookingMapping::where('organization_id', $org->id)->count());
        $this->assertSame($baselineChecksum, $this->computeDatasetChecksum());
    }
}
