<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Models\User;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Services\Finance\FinancialPostingService;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class FinancialPostingBalancedAndRetrySafeTest extends TestCase
{
    use RefreshDatabase;

    private FinancialPostingService $financials;
    private Organization $organization;
    private Venue $venue;
    private Court $court;
    private User $user;
    private WalletAccount $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->financials = app(FinancialPostingService::class);
        $this->organization = Organization::create(['name' => 'Finance Test Org', 'slug' => 'fin-org-' . uniqid()]);
        $this->venue = Venue::create(['organization_id' => $this->organization->id, 'name' => 'Finance Venue', 'slug' => 'fin-ven-' . uniqid(), 'is_active' => true]);
        $this->court = Court::create(['venue_id' => $this->venue->id, 'name' => 'Court A', 'is_active' => true]);
        $this->user = User::factory()->create();
        $this->wallet = WalletAccount::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'currency' => 'VND',
            'balance' => 1000000,
            'status' => 'active',
        ]);
    }

    public function test_wallet_booking_payment_and_refund_are_balanced_and_retry_safe(): void
    {
        $booking = Booking::create([
            'code' => 'BK-POST-01',
            'user_id' => $this->user->id,
            'court_id' => $this->court->id,
            'total_amount' => 300000,
            'status' => 'confirmed',
        ]);
        $payment = $booking->payments()->create([
            'provider' => 'wallet',
            'external_transaction_id' => 'tx-post-01',
            'amount' => 300000,
            'status' => 'paid',
        ]);

        // First payment post
        $paymentJournal1 = $this->financials->postWalletBookingPayment($booking->load('court.venue'), $payment, $this->wallet->id);
        $this->assertSame(300000, $paymentJournal1->total_debit);
        $this->assertSame(300000, $paymentJournal1->total_credit);
        $this->assertSame('booking.wallet_payment', $paymentJournal1->event_type);

        // Retry payment post returns identical journal
        $paymentJournal2 = $this->financials->postWalletBookingPayment($booking->load('court.venue'), $payment, $this->wallet->id);
        $this->assertSame($paymentJournal1->id, $paymentJournal2->id);

        // First refund post
        $refundJournal1 = $this->financials->postWalletBookingRefund($booking->load('court.venue'), 300000, $this->wallet->id, 'ref-01');
        $this->assertSame(300000, $refundJournal1->total_debit);
        $this->assertSame(300000, $refundJournal1->total_credit);
        $this->assertSame('booking.wallet_refund', $refundJournal1->event_type);

        // Retry refund post returns identical journal
        $refundJournal2 = $this->financials->postWalletBookingRefund($booking->load('court.venue'), 300000, $this->wallet->id, 'ref-01');
        $this->assertSame($refundJournal1->id, $refundJournal2->id);
    }

    public function test_wallet_reservation_payment_and_refund_are_balanced_and_retry_safe(): void
    {
        $reservation = Reservation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'court_id' => $this->court->id,
            'code' => 'RES-POST-01',
            'status' => 'confirmed',
            'total_amount' => 450000,
        ]);

        // Payment post
        $payJournal1 = $this->financials->postWalletReservationPayment($reservation, 450000, $this->wallet->id, 999);
        $this->assertSame(450000, $payJournal1->total_debit);
        $this->assertSame(450000, $payJournal1->total_credit);
        $this->assertSame('reservation.wallet_payment', $payJournal1->event_type);

        // Retry payment post
        $payJournal2 = $this->financials->postWalletReservationPayment($reservation, 450000, $this->wallet->id, 999);
        $this->assertSame($payJournal1->id, $payJournal2->id);

        // Refund post
        $refJournal1 = $this->financials->postWalletReservationRefund($reservation, 450000, $this->wallet->id, 'res-ref-01');
        $this->assertSame(450000, $refJournal1->total_debit);
        $this->assertSame(450000, $refJournal1->total_credit);
        $this->assertSame('reservation.wallet_refund', $refJournal1->event_type);

        // Retry refund post
        $refJournal2 = $this->financials->postWalletReservationRefund($reservation, 450000, $this->wallet->id, 'res-ref-01');
        $this->assertSame($refJournal1->id, $refJournal2->id);
    }

    public function test_pos_sales_with_wallet_and_cash_are_balanced_and_retry_safe(): void
    {
        // POS sale via wallet
        $posWallet1 = $this->financials->postPosSale($this->organization->id, 101, 150000, 'wallet', 'POS-ORD-101');
        $this->assertSame(150000, $posWallet1->total_debit);
        $this->assertSame(150000, $posWallet1->total_credit);
        $this->assertSame('pos.sale', $posWallet1->event_type);

        // Retry POS sale via wallet
        $posWallet2 = $this->financials->postPosSale($this->organization->id, 101, 150000, 'wallet', 'POS-ORD-101');
        $this->assertSame($posWallet1->id, $posWallet2->id);

        // POS sale via cash
        $posCash1 = $this->financials->postPosSale($this->organization->id, 102, 200000, 'cash', 'POS-ORD-102');
        $this->assertSame(200000, $posCash1->total_debit);
        $this->assertSame(200000, $posCash1->total_credit);
        $this->assertSame('pos.sale', $posCash1->event_type);

        // Retry POS sale via cash
        $posCash2 = $this->financials->postPosSale($this->organization->id, 102, 200000, 'cash', 'POS-ORD-102');
        $this->assertSame($posCash1->id, $posCash2->id);
    }

    public function test_tournament_registration_payment_is_balanced_and_retry_safe(): void
    {
        $tournament = Tournament::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->user->id,
            'name' => 'Summer Badminton Open',
            'format' => 'single_elimination',
            'status' => 'open',
            'settings' => ['entry_fee' => '250000'],
        ]);

        $participant = TournamentParticipant::create([
            'organization_id' => $this->organization->id,
            'tournament_id' => $tournament->id,
            'user_id' => $this->user->id,
            'seed' => 1,
            'status' => 'registered',
        ]);

        // First post
        $tournJournal1 = $this->financials->postTournamentRegistrationPayment(
            $this->organization->id,
            $tournament->id,
            $participant->id,
            250000,
            $this->wallet->id,
            'TRN-REG-01'
        );

        $this->assertSame(250000, $tournJournal1->total_debit);
        $this->assertSame(250000, $tournJournal1->total_credit);
        $this->assertSame('tournament.registration', $tournJournal1->event_type);

        // Check journal entries have balanced debits and credits
        $totalDebit = $tournJournal1->entries->where('side', 'debit')->sum('amount');
        $totalCredit = $tournJournal1->entries->where('side', 'credit')->sum('amount');
        $this->assertSame(250000, $totalDebit);
        $this->assertSame(250000, $totalCredit);

        // Retry post returns identical journal
        $tournJournal2 = $this->financials->postTournamentRegistrationPayment(
            $this->organization->id,
            $tournament->id,
            $participant->id,
            250000,
            $this->wallet->id,
            'TRN-REG-01'
        );
        $this->assertSame($tournJournal1->id, $tournJournal2->id);
    }

    public function test_tenant_wallet_deposit_and_spend_are_balanced_and_retry_safe(): void
    {
        // Deposit
        $depositJournal1 = $this->financials->postWalletDeposit(
            $this->organization->id,
            $this->wallet->id,
            500000,
            'DEP-REF-100'
        );
        $this->assertSame(500000, $depositJournal1->total_debit);
        $this->assertSame(500000, $depositJournal1->total_credit);
        $this->assertSame('wallet.deposit', $depositJournal1->event_type);

        // Retry deposit
        $depositJournal2 = $this->financials->postWalletDeposit(
            $this->organization->id,
            $this->wallet->id,
            500000,
            'DEP-REF-100'
        );
        $this->assertSame($depositJournal1->id, $depositJournal2->id);

        // Spend
        $spendJournal1 = $this->financials->postWalletSpend(
            $this->organization->id,
            $this->wallet->id,
            75000,
            'SPEND-REF-100'
        );
        $this->assertSame(75000, $spendJournal1->total_debit);
        $this->assertSame(75000, $spendJournal1->total_credit);
        $this->assertSame('wallet.spend', $spendJournal1->event_type);

        // Retry spend
        $spendJournal2 = $this->financials->postWalletSpend(
            $this->organization->id,
            $this->wallet->id,
            75000,
            'SPEND-REF-100'
        );
        $this->assertSame($spendJournal1->id, $spendJournal2->id);
    }

    public function test_unbalanced_journals_are_strictly_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LedgerService::class)->post([
            'organization_id' => $this->organization->id,
            'event_type' => 'corrupt.post',
            'event_id' => 'corrupt-1',
        ], [
            ['side' => 'debit', 'amount' => 50000],
            ['side' => 'credit', 'amount' => 49999],
        ]);
    }
}
