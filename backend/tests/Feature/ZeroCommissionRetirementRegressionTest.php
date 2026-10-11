<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Journal;
use App\Models\MarketplaceListing;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Services\Finance\FinancialPostingService;
use App\Services\Finance\LedgerService;
use App\Services\Finance\SettlementService;
use App\Services\Social\MarketplaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZeroCommissionRetirementRegressionTest extends TestCase
{
    use RefreshDatabase;

    private FinancialPostingService $financials;

    private LedgerService $ledger;

    private MarketplaceService $marketplace;

    private SettlementService $settlements;

    private Organization $organization;

    private Venue $venue;

    private Court $court;

    private User $seller;

    private User $buyer;

    private WalletAccount $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->financials = app(FinancialPostingService::class);
        $this->ledger = app(LedgerService::class);
        $this->marketplace = app(MarketplaceService::class);
        $this->settlements = app(SettlementService::class);

        $this->organization = Organization::create([
            'name' => 'Zero Commission Org',
            'slug' => 'zero-comm-org-'.uniqid(),
        ]);
        $this->venue = Venue::create([
            'organization_id' => $this->organization->id,
            'name' => 'Zero Comm Venue',
            'slug' => 'zero-comm-ven-'.uniqid(),
            'is_active' => true,
        ]);
        $this->court = Court::create([
            'venue_id' => $this->venue->id,
            'name' => 'Court Zero',
            'is_active' => true,
        ]);
        $this->seller = User::factory()->create();
        $this->buyer = User::factory()->create();
        $this->wallet = WalletAccount::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->buyer->id,
            'currency' => 'VND',
            'balance' => 1000000,
            'status' => 'active',
        ]);
    }

    public function test_zero_commission_on_booking_wallet_payment_despite_legacy_configuration_and_metadata(): void
    {
        config(['finance.commission_bps' => 500]);

        $booking = Booking::create([
            'code' => 'BK-ZERO-01',
            'user_id' => $this->buyer->id,
            'court_id' => $this->court->id,
            'total_amount' => 200000,
            'status' => 'confirmed',
        ]);
        $payment = $booking->payments()->create([
            'provider' => 'wallet',
            'external_transaction_id' => 'tx-zero-01',
            'amount' => 200000,
            'status' => 'paid',
        ]);

        $journal = $this->financials->postWalletBookingPayment(
            $booking->load('court.venue'),
            $payment,
            $this->wallet->id
        );

        $accounts = $this->ledger->systemAccounts($this->organization->id);

        $this->assertSame(200000, $journal->total_debit);
        $this->assertSame(200000, $journal->total_credit);

        // Wallet liability debited full 200,000
        $debitEntry = $journal->entries()->where('account_id', $accounts['wallet_liability']->id)->first();
        $this->assertNotNull($debitEntry);
        $this->assertSame('debit', $debitEntry->side);
        $this->assertSame(200000, $debitEntry->amount);

        // Tenant payable credited full 200,000
        $payableEntry = $journal->entries()->where('account_id', $accounts['tenant_payable']->id)->first();
        $this->assertNotNull($payableEntry);
        $this->assertSame('credit', $payableEntry->side);
        $this->assertSame(200000, $payableEntry->amount);

        // No platform commission entry
        $commissionEntry = $journal->entries()->where('account_id', $accounts['platform_commission']->id)->first();
        $this->assertNull($commissionEntry);

        // Metadata commission is 0
        $this->assertSame(0, $journal->metadata['commission'] ?? null);
    }

    public function test_zero_commission_on_reservation_wallet_payment_despite_legacy_configuration(): void
    {
        config(['finance.commission_bps' => 500]);

        $reservation = Reservation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->buyer->id,
            'court_id' => $this->court->id,
            'code' => 'RES-ZERO-01',
            'status' => 'confirmed',
            'total_amount' => 200000,
        ]);

        $journal = $this->financials->postWalletReservationPayment(
            $reservation,
            200000,
            $this->wallet->id,
            12345
        );

        $accounts = $this->ledger->systemAccounts($this->organization->id);

        $this->assertSame(200000, $journal->total_debit);
        $this->assertSame(200000, $journal->total_credit);

        $payableEntry = $journal->entries()->where('account_id', $accounts['tenant_payable']->id)->first();
        $this->assertNotNull($payableEntry);
        $this->assertSame('credit', $payableEntry->side);
        $this->assertSame(200000, $payableEntry->amount);

        $commissionEntry = $journal->entries()->where('account_id', $accounts['platform_commission']->id)->first();
        $this->assertNull($commissionEntry);

        $this->assertSame(0, $journal->metadata['commission'] ?? null);
    }

    public function test_zero_commission_on_tournament_registration_despite_legacy_configuration(): void
    {
        config(['finance.commission_bps' => 500]);

        $tournament = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->seller->id,
            'name' => 'Zero Commission Cup',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => ['entry_fee' => '200000'],
        ]);

        $participant = TournamentParticipant::create([
            'organization_id' => $this->organization->id,
            'tournament_id' => $tournament->id,
            'user_id' => $this->buyer->id,
            'seed' => 1,
            'status' => 'registered',
            'payment_status' => 'paid',
        ]);

        $journal = $this->financials->postTournamentRegistrationPayment(
            $this->organization->id,
            $tournament->id,
            $participant->id,
            200000,
            $this->wallet->id,
            'tour-zero-01'
        );

        $accounts = $this->ledger->systemAccounts($this->organization->id);

        $this->assertSame(200000, $journal->total_debit);
        $this->assertSame(200000, $journal->total_credit);

        $payableEntry = $journal->entries()->where('account_id', $accounts['tenant_payable']->id)->first();
        $this->assertSame(200000, $payableEntry->amount);

        $commissionEntry = $journal->entries()->where('account_id', $accounts['platform_commission']->id)->first();
        $this->assertNull($commissionEntry);

        $this->assertSame(0, $journal->metadata['commission'] ?? null);
    }

    public function test_provider_collection_retains_provider_fee_without_commission_despite_legacy_configuration_and_supplied_metadata(): void
    {
        config([
            'finance.commission_bps' => 500,
            'finance.provider_fee_bps' => 100, // 1% = 2,000 on 200,000
        ]);

        $reservation = Reservation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->buyer->id,
            'court_id' => $this->court->id,
            'code' => 'RES-PROV-01',
            'status' => 'confirmed',
            'total_amount' => 200000,
        ]);

        $journal = $this->financials->postReservationPayment(
            $reservation,
            200000,
            'pay-ref-prov-01',
            ['commission' => 10000] // Stale caller metadata attempt
        );

        $accounts = $this->ledger->systemAccounts($this->organization->id);

        $this->assertSame(200000, $journal->total_debit);
        $this->assertSame(200000, $journal->total_credit);

        // Cash clearing debited 200,000
        $cashEntry = $journal->entries()->where('account_id', $accounts['cash_clearing']->id)->first();
        $this->assertSame('debit', $cashEntry->side);
        $this->assertSame(200000, $cashEntry->amount);

        // Provider fee credited 2,000
        $feeEntry = $journal->entries()->where('account_id', $accounts['provider_fee_expense']->id)->first();
        $this->assertNotNull($feeEntry);
        $this->assertSame('credit', $feeEntry->side);
        $this->assertSame(2000, $feeEntry->amount);

        // Tenant payable credited 198,000 (200,000 - 2,000)
        $payableEntry = $journal->entries()->where('account_id', $accounts['tenant_payable']->id)->first();
        $this->assertNotNull($payableEntry);
        $this->assertSame('credit', $payableEntry->side);
        $this->assertSame(198000, $payableEntry->amount);

        // No commission entry
        $commissionEntry = $journal->entries()->where('account_id', $accounts['platform_commission']->id)->first();
        $this->assertNull($commissionEntry);

        // Canonical zero commission in metadata overrides caller metadata
        $this->assertSame(0, $journal->metadata['commission']);
        $this->assertSame(2000, $journal->metadata['provider_fee']);
    }

    public function test_unsold_marketplace_listing_with_stored_five_percent_fee_purchases_commission_free(): void
    {
        config(['marketplace.fee_bps' => 500]);

        $reservation = Reservation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->seller->id,
            'court_id' => $this->court->id,
            'code' => 'RES-MKT-01',
            'status' => 'confirmed',
            'total_amount' => 200000,
        ]);
        $slot = ReservationSlot::create([
            'organization_id' => $this->organization->id,
            'reservation_id' => $reservation->id,
            'court_id' => $this->court->id,
            'starts_at' => now()->addHours(5),
            'ends_at' => now()->addHours(6),
            'unit_price' => 200000,
            'status' => 'confirmed',
        ]);
        $entitlement = ReservationEntitlement::create([
            'organization_id' => $this->organization->id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'user_id' => $this->seller->id,
            'court_id' => $this->court->id,
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->ends_at,
            'amount' => 200000,
            'status' => 'active',
        ]);

        // Unsold listing with stored 5% fee_bps
        $listing = MarketplaceListing::create([
            'organization_id' => $this->organization->id,
            'reservation_entitlement_id' => $entitlement->id,
            'seller_id' => $this->seller->id,
            'original_price' => 200000,
            'asking_price' => 200000,
            'fee_bps' => 500, // Legacy 5% stored on listing
            'status' => 'listed',
            'cutoff_at' => $entitlement->starts_at->copy()->subHours(2),
        ]);

        $transfer = $this->marketplace->purchase(
            $listing,
            $this->buyer,
            'idemp-mkt-zero-01',
            'buyer-pay-ref-01',
            'seller-ref-ref-01',
            true
        );

        $this->assertSame('completed', $transfer->status);
        $this->assertSame(200000, $transfer->buyer_amount);
        $this->assertSame(0, $transfer->marketplace_fee);
        $this->assertSame(200000, $transfer->seller_refund);

        $accounts = $this->ledger->systemAccounts($this->organization->id);
        $journal = Journal::where('event_type', 'marketplace.sale')
            ->where('event_id', "marketplace:{$listing->id}")
            ->firstOrFail();

        $this->assertSame(200000, $journal->total_debit);
        $this->assertSame(200000, $journal->total_credit);

        $commissionEntry = $journal->entries()->where('account_id', $accounts['platform_commission']->id)->first();
        $this->assertNull($commissionEntry);
        $this->assertSame(0, $journal->metadata['marketplace_fee']);
    }

    public function test_replayed_historical_event_preserves_original_journal_and_commission(): void
    {
        $accounts = $this->ledger->systemAccounts($this->organization->id);

        // Record a historical journal with 10,000 commission
        $historicalJournal = $this->ledger->post([
            'organization_id' => $this->organization->id,
            'event_type' => 'booking.wallet_payment',
            'event_id' => 'booking:999:payment:888',
            'description' => 'Historical booking payment',
            'metadata' => ['commission' => 10000],
        ], [
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'debit', 'amount' => 200000],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => 190000],
            ['account_id' => $accounts['platform_commission']->id, 'side' => 'credit', 'amount' => 10000],
        ]);

        $this->assertSame(200000, $historicalJournal->total_debit);
        $this->assertSame(200000, $historicalJournal->total_credit);

        // Replay/lookup returns identical journal without recalculating
        $replayed = Journal::where('organization_id', $this->organization->id)
            ->where('event_type', 'booking.wallet_payment')
            ->where('event_id', 'booking:999:payment:888')
            ->first();

        $this->assertSame($historicalJournal->id, $replayed->id);
        $this->assertSame(10000, $replayed->entries()->where('account_id', $accounts['platform_commission']->id)->first()->amount);
    }

    public function test_authorized_compensation_uses_original_journal_allocations(): void
    {
        $accounts = $this->ledger->systemAccounts($this->organization->id);

        // Historical journal with 10,000 commission
        $original = $this->ledger->post([
            'organization_id' => $this->organization->id,
            'event_type' => 'booking.wallet_payment',
            'event_id' => 'booking:comp:payment:1',
            'description' => 'Historical booking payment',
            'metadata' => ['commission' => 10000],
        ], [
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'debit', 'amount' => 200000],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => 190000],
            ['account_id' => $accounts['platform_commission']->id, 'side' => 'credit', 'amount' => 10000],
        ]);

        // Compensate reverses exact entries
        $compensated = $this->financials->compensate($original, 'comp:event:1', 'Authorized refund compensation');

        $this->assertSame(200000, $compensated->total_debit);
        $this->assertSame(200000, $compensated->total_credit);
        $this->assertSame('compensation', $compensated->event_type);

        // Debits tenant payable 190,000 and platform commission 10,000; credits wallet liability 200,000
        $revCommission = $compensated->entries()->where('account_id', $accounts['platform_commission']->id)->first();
        $this->assertNotNull($revCommission);
        $this->assertSame('debit', $revCommission->side);
        $this->assertSame(10000, $revCommission->amount);

        $revPayable = $compensated->entries()->where('account_id', $accounts['tenant_payable']->id)->first();
        $this->assertSame('debit', $revPayable->side);
        $this->assertSame(190000, $revPayable->amount);

        $revWallet = $compensated->entries()->where('account_id', $accounts['wallet_liability']->id)->first();
        $this->assertSame('credit', $revWallet->side);
        $this->assertSame(200000, $revWallet->amount);
    }

    public function test_settlement_snapshots_and_mixed_periods(): void
    {
        $accounts = $this->ledger->systemAccounts($this->organization->id);
        $periodStart = now()->subDays(5)->startOfDay();
        $periodEnd = now()->addDays(5)->endOfDay();

        // Historical journal in period with 15,000 commission
        $this->ledger->post([
            'organization_id' => $this->organization->id,
            'event_type' => 'booking.payment',
            'event_id' => 'historical:01',
            'currency' => 'VND',
            'posted_at' => now()->subDay(),
        ], [
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'debit', 'amount' => 300000],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => 285000],
            ['account_id' => $accounts['platform_commission']->id, 'side' => 'credit', 'amount' => 15000],
        ]);

        // Post-retirement journal in period with 0 commission
        $this->ledger->post([
            'organization_id' => $this->organization->id,
            'event_type' => 'booking.payment',
            'event_id' => 'new:01',
            'currency' => 'VND',
            'posted_at' => now(),
        ], [
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'debit', 'amount' => 200000],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => 200000],
        ]);

        $settlement = $this->settlements->snapshot($this->organization, $periodStart, $periodEnd);

        // Gross = 300,000 + 200,000 = 500,000
        $this->assertSame(500000, $settlement->gross_amount);
        // Historical commission = 15,000 (new added 0)
        $this->assertSame(15000, $settlement->commission_amount);
        // Tenant payable = 285,000 + 200,000 = 485,000
        $this->assertSame(485000, $settlement->tenant_payable);

        // Snapshot is immutable
        $this->expectException(\LogicException::class);
        $settlement->update(['commission_amount' => 0]);
    }
}
