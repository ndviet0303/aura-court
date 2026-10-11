<?php

namespace Tests\Feature;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Payment\PayoutStatusResponse;
use App\Contracts\Payment\PayoutSubmissionResponse;
use App\Models\Beneficiary;
use App\Models\Booking;
use App\Models\Court;
use App\Models\FinanceException;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Payout;
use App\Models\ProviderEvent;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Venue;
use App\Services\Finance\FinancialPostingService;
use App\Services\Finance\LedgerService;
use App\Services\Finance\PayoutService;
use App\Services\Finance\ReconciliationService;
use App\Services\Payment\PaymentProviderManager;
use App\Services\Payment\ProviderOperationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayoutLifecycleThresholdEligibilityReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    private Beneficiary $beneficiary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Aura Arena Finance Org',
            'slug' => 'aura-finance-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'finance.minimum_payout' => 500000,
            'finance.currency' => 'VND',
            'finance.settlement_delay_days' => 2,
            'services.payments.default_provider' => 'payos',
        ]);

        $this->owner = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
        ]);

        $this->beneficiary = Beneficiary::create([
            'organization_id' => $this->organization->id,
            'provider' => 'payos',
            'bank_code' => '970415',
            'account_number' => '1029384756',
            'account_holder' => 'AURA ARENA CO',
            'provider_beneficiary_id' => 'ben-aura-123',
            'status' => 'verified',
        ]);
    }

    public function test_payout_creation_enforces_minimum_threshold(): void
    {
        $settlement = Settlement::create([
            'organization_id' => $this->organization->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'gross_amount' => 450000,
            'tenant_payable' => 400000, // Below 500,000 VND
            'currency' => 'VND',
            'status' => 'eligible',
            'eligible_at' => now()->subDay(),
            'snapshot' => ['tenant_payable' => 400000],
        ]);

        $payoutService = app(PayoutService::class);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Settlement is below the minimum payout threshold.');

        $payoutService->createDraft($settlement, $this->beneficiary);
    }

    public function test_payout_creation_enforces_eligibility_and_settlement_delay(): void
    {
        // Settlement whose eligible_at is in the future (within D+2 window)
        $settlement = Settlement::create([
            'organization_id' => $this->organization->id,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-07',
            'gross_amount' => 5000000,
            'tenant_payable' => 4500000,
            'currency' => 'VND',
            'status' => 'pending',
            'eligible_at' => now()->addDay(), // Not yet reached D+2
            'snapshot' => ['tenant_payable' => 4500000],
        ]);

        $payoutService = app(PayoutService::class);

        try {
            $payoutService->createDraft($settlement, $this->beneficiary);
            $this->fail('Expected HttpException 409 for ineligible settlement.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertStringContainsString('Settlement is not yet eligible for payout.', $e->getMessage());
        }

        // Now advance eligible_at to past
        $settlement->update([
            'status' => 'eligible',
            'eligible_at' => now()->subHour(),
        ]);

        $payout = $payoutService->createDraft($settlement, $this->beneficiary);
        $this->assertSame('draft', $payout->status);
        $this->assertSame(4500000, $payout->amount);
    }

    public function test_payout_failed_provider_response_records_exception_and_preserves_ledger(): void
    {
        $settlement = Settlement::create([
            'organization_id' => $this->organization->id,
            'period_start' => '2026-08-10',
            'period_end' => '2026-08-17',
            'gross_amount' => 10000000,
            'tenant_payable' => 9000000,
            'currency' => 'VND',
            'status' => 'eligible',
            'eligible_at' => now()->subDays(2),
            'snapshot' => ['tenant_payable' => 9000000],
        ]);

        $payoutService = app(PayoutService::class);
        $payout = $payoutService->createDraft($settlement, $this->beneficiary);
        $payout = $payoutService->approve($payout);

        // Mock payment provider with FAILED payout status
        $mockProvider = Mockery::mock(PaymentProviderInterface::class);
        $mockProvider->shouldReceive('name')->andReturn('payos');
        $mockProvider->shouldReceive('submitPayout')->once()->andReturn(new PayoutSubmissionResponse(
            provider: 'payos',
            providerPayoutId: 'ext-payout-fail-001',
            status: 'SUBMITTED',
            amount: 9000000,
            currency: 'VND',
            raw: ['id' => 'ext-payout-fail-001'],
        ));
        $mockProvider->shouldReceive('queryPayoutStatus')->once()->andReturn(new PayoutStatusResponse(
            provider: 'payos',
            providerPayoutId: 'ext-payout-fail-001',
            status: 'FAILED',
            failureCode: 'BANK_REJECTED',
            failureMessage: 'Tài khoản thụ hưởng đã đóng hoặc bị khóa',
            raw: ['status' => 'FAILED', 'error' => 'BANK_REJECTED'],
        ));

        $providerManager = new PaymentProviderManager($mockProvider);
        $this->app->instance(PaymentProviderManager::class, $providerManager);
        $this->app->instance(ProviderOperationService::class, new ProviderOperationService($providerManager));
        $payoutService = app(PayoutService::class);
        $initialJournalCount = Journal::where('organization_id', $this->organization->id)->count();

        // Submit
        $payout = $payoutService->submit($payout);
        $this->assertSame('submitted', $payout->status);

        // Sync status -> FAILED
        $payout = $payoutService->syncStatus($payout);
        $this->assertSame('failed', $payout->status);
        $this->assertNotNull($payout->failed_at);
        $this->assertSame('Tài khoản thụ hưởng đã đóng hoặc bị khóa', $payout->failure_reason);

        // Exception created for audit
        $exception = FinanceException::where('organization_id', $this->organization->id)
            ->where('type', 'payout_failed')
            ->where('reference', (string) $payout->id)
            ->first();

        $this->assertNotNull($exception);
        $this->assertSame('open', $exception->status);
        $this->assertSame('high', $exception->severity);
        $this->assertSame(9000000, $exception->amount);
        $this->assertSame($payout->id, $exception->links['payout_id']);
        $this->assertTrue($exception->due_at->greaterThanOrEqualTo(now()->addMinutes(14)));

        // Ledger is strictly preserved
        $this->assertSame($initialJournalCount, Journal::where('organization_id', $this->organization->id)->count());
    }

    public function test_payout_reversed_provider_response_records_critical_exception(): void
    {
        $settlement = Settlement::create([
            'organization_id' => $this->organization->id,
            'period_start' => '2026-08-10',
            'period_end' => '2026-08-17',
            'gross_amount' => 8000000,
            'tenant_payable' => 7200000,
            'currency' => 'VND',
            'status' => 'eligible',
            'eligible_at' => now()->subDays(2),
            'snapshot' => ['tenant_payable' => 7200000],
        ]);

        $payoutService = app(PayoutService::class);
        $payout = $payoutService->createDraft($settlement, $this->beneficiary);
        $payout = $payoutService->approve($payout);

        $mockProvider = Mockery::mock(PaymentProviderInterface::class);
        $mockProvider->shouldReceive('name')->andReturn('payos');
        $mockProvider->shouldReceive('submitPayout')->once()->andReturn(new PayoutSubmissionResponse(
            provider: 'payos',
            providerPayoutId: 'ext-payout-rev-002',
            status: 'SUBMITTED',
            amount: 7200000,
            currency: 'VND',
            raw: ['id' => 'ext-payout-rev-002'],
        ));
        $mockProvider->shouldReceive('queryPayoutStatus')->once()->andReturn(new PayoutStatusResponse(
            provider: 'payos',
            providerPayoutId: 'ext-payout-rev-002',
            status: 'REVERSED',
            failureCode: 'REVERSED_BY_CLEARING_HOUSE',
            failureMessage: 'Giao dịch bị hoàn trả bởi cổng thanh toán',
            raw: ['status' => 'REVERSED'],
        ));

        $providerManager = new PaymentProviderManager($mockProvider);
        $this->app->instance(PaymentProviderManager::class, $providerManager);
        $this->app->instance(ProviderOperationService::class, new ProviderOperationService($providerManager));
        $payoutService = app(PayoutService::class);
        $payout = $payoutService->submit($payout);
        $payout = $payoutService->syncStatus($payout);

        $this->assertSame('reversed', $payout->status);
        $this->assertNotNull($payout->reversed_at);

        $exception = FinanceException::where('organization_id', $this->organization->id)
            ->where('type', 'payout_reversed')
            ->where('reference', (string) $payout->id)
            ->first();

        $this->assertNotNull($exception);
        $this->assertSame('critical', $exception->severity);
        $this->assertSame(7200000, $exception->amount);
    }

    public function test_daily_reconciliation_detects_mismatch_and_preserves_ledger_when_resolved(): void
    {
        $reconcileService = app(ReconciliationService::class);
        $today = now()->toDateString();

        // 1. Provider event of 1,000,000 VND
        ProviderEvent::create([
            'organization_id' => $this->organization->id,
            'provider' => 'payos',
            'event_id' => 'rec-event-'.uniqid(),
            'type' => 'payment.status',
            'status' => 'PAID',
            'amount' => 1000000,
            'payload' => ['amount' => 1000000],
            'verified_at' => now(),
        ]);

        // 2. Journal entry of only 800,000 VND (creating a 200,000 VND mismatch)
        $accounts = app(LedgerService::class)->systemAccounts($this->organization->id);
        app(LedgerService::class)->post([
            'organization_id' => $this->organization->id,
            'event_type' => 'wallet.deposit',
            'event_id' => 'dep-'.uniqid(),
        ], [
            ['account_id' => $accounts['cash_clearing']->id, 'side' => 'debit', 'amount' => 800000],
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'credit', 'amount' => 800000],
        ]);

        $run = $reconcileService->run($this->organization);

        $this->assertSame('mismatch', $run->status);
        $this->assertSame(200000, $run->mismatch_amount);

        // Exception created within 15 minutes due date
        $exception = FinanceException::where('organization_id', $this->organization->id)
            ->where('type', 'reconciliation_mismatch')
            ->where('reference', "reconciliation:{$today}")
            ->first();

        $this->assertNotNull($exception);
        $this->assertSame('open', $exception->status);
        $this->assertSame(200000, $exception->amount);
        $this->assertTrue($exception->due_at->greaterThanOrEqualTo(now()->addMinutes(14)));

        // Capture ledger snapshot before resolution
        $journalsBefore = Journal::where('organization_id', $this->organization->id)->get();

        // Resolve exception
        $resolved = $reconcileService->resolve(
            $exception,
            $this->owner->id,
            'Đã đối chiếu với sao kê ngân hàng và xác nhận chênh lệch do giao dịch cắt ca.'
        );

        $this->assertSame('resolved', $resolved->status);
        $this->assertSame($this->owner->id, $resolved->resolved_by);

        // The resolution SHALL NOT alter or delete previous ledger records
        $journalsAfter = Journal::where('organization_id', $this->organization->id)->get();
        $this->assertCount($journalsBefore->count(), $journalsAfter);
        $this->assertSame(
            $journalsBefore->pluck('id')->sort()->values()->all(),
            $journalsAfter->pluck('id')->sort()->values()->all()
        );
    }

    public function test_compensating_records_do_not_mutate_original_payment_journal(): void
    {
        $venue = Venue::create([
            'organization_id' => $this->organization->id,
            'name' => 'Aura Court 1',
            'slug' => 'aura-court-1-'.uniqid(),
            'is_active' => true,
        ]);
        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Court A',
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $booking = Booking::create([
            'code' => 'BKCOMP001',
            'user_id' => $user->id,
            'court_id' => $court->id,
            'total_amount' => 300000,
            'status' => 'confirmed',
        ]);
        $payment = $booking->payments()->create([
            'provider' => 'wallet',
            'external_transaction_id' => 'w-comp-tx-1',
            'amount' => 300000,
            'status' => 'paid',
        ]);

        $financials = app(FinancialPostingService::class);

        // Original booking payment journal
        $paymentJournal = $financials->postWalletBookingPayment($booking->load('court.venue'), $payment, 1);
        $paymentJournalId = $paymentJournal->id;
        $originalDebit = $paymentJournal->total_debit;
        $originalCredit = $paymentJournal->total_credit;

        // Refund occurs: post compensating journal
        $refundJournal = $financials->postWalletBookingRefund($booking->load('court.venue'), 300000, 1);

        // Assert compensating journal is balanced
        $this->assertSame(300000, $refundJournal->total_debit);
        $this->assertSame($refundJournal->total_debit, $refundJournal->total_credit);
        $this->assertSame('booking.wallet_refund', $refundJournal->event_type);

        // Assert original payment journal remains completely unchanged
        $freshPaymentJournal = Journal::findOrFail($paymentJournalId);
        $this->assertSame($originalDebit, $freshPaymentJournal->total_debit);
        $this->assertSame($originalCredit, $freshPaymentJournal->total_credit);
        $this->assertSame('booking.wallet_payment', $freshPaymentJournal->event_type);
    }
}
