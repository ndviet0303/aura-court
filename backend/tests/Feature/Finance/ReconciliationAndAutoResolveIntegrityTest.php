<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\Actions\AutoResolveReconciliation;
use App\Domain\Finance\DTOs\AutoResolveReconciliationData;
use App\Models\FinanceException;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundObligation;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformProviderReceipt;
use App\Models\ReceivingConnection;
use App\Models\ReceivingConnectionEvidence;
use App\Models\User;
use App\Models\Venue;
use App\Services\Finance\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReconciliationAndAutoResolveIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Venue $venue;

    private ReceivingConnection $connection;

    private ReceivingConnectionEvidence $evidence;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::create([
            'name' => 'Integrity Reconcile Org',
            'slug' => 'integrity-rec-'.uniqid(),
            'status' => 'active',
        ]);

        config([
            'features.flags.marketplace_payouts' => true,
            'features.pilot_organizations' => array_merge(
                (array) config('features.pilot_organizations', []),
                [(string) $this->organization->id]
            ),
        ]);

        $this->owner = User::factory()->create();
        OrganizationMember::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);

        $this->venue = Venue::create([
            'organization_id' => $this->organization->id,
            'name' => 'Integrity Center',
            'slug' => 'integrity-center-'.uniqid(),
            'is_active' => true,
        ]);

        $this->connection = ReceivingConnection::create([
            'organization_id' => $this->organization->id,
            'venue_id' => $this->venue->id,
            'owner_user_id' => $this->owner->id,
            'provider' => 'sepay',
            'routing_id' => (string) Str::uuid(),
            'bank_code' => 'MBBank',
            'account_name_ciphertext' => Crypt::encryptString('Test Owner'),
            'account_number_ciphertext' => Crypt::encryptString('0123456789'),
            'account_last_four' => '6789',
            'status' => ReceivingConnection::STATUS_VERIFIED,
            'credential_version' => 1,
            'verified_at' => now(),
        ]);

        $this->evidence = ReceivingConnectionEvidence::create([
            'receiving_connection_id' => $this->connection->id,
            'actor_id' => $this->owner->id,
            'event_type' => 'webhook_verification',
            'credential_version' => 1,
            'event_fingerprint' => hash('sha256', 'setup-evidence-'.uniqid()),
            'evidence_ciphertext' => Crypt::encryptString('verified'),
        ]);
    }

    private function createAttempt(string $purpose, string $purposeId, int $amount, string $status = 'paid'): OwnerPaymentAttempt
    {
        return OwnerPaymentAttempt::create([
            'organization_id' => $this->organization->id,
            'venue_id' => $this->venue->id,
            'user_id' => $this->owner->id,
            'receiving_connection_id' => $this->connection->id,
            'payment_model' => 'direct_owner_v1',
            'provider' => 'sepay',
            'purpose' => $purpose,
            'purpose_id' => $purposeId,
            'reference' => 'REF-'.uniqid(),
            'idempotency_key' => 'IDEM-'.uniqid(),
            'request_fingerprint' => hash('sha256', uniqid()),
            'amount' => $amount,
            'currency' => 'VND',
            'status' => $status,
            'credential_version' => 1,
            'receiver_snapshot_ciphertext' => Crypt::encryptString(json_encode(['name' => 'Owner', 'account' => '0123456789'])),
            'purpose_snapshot_ciphertext' => Crypt::encryptString(json_encode(['purpose' => $purpose])),
            'expires_at' => now()->addHour(),
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    private function createReceipt(?OwnerPaymentAttempt $attempt, int $amount, string $status = OwnerPaymentReceipt::STATUS_ALLOCATED): OwnerPaymentReceipt
    {
        $id = uniqid();

        return OwnerPaymentReceipt::create([
            'organization_id' => $this->organization->id,
            'venue_id' => $this->venue->id,
            'receiving_connection_id' => $this->connection->id,
            'receiving_connection_evidence_id' => $this->evidence->id,
            'payment_attempt_id' => $attempt?->id,
            'provider' => 'sepay',
            'source' => 'webhook',
            'transfer_type' => 'in',
            'provider_event_id' => 'pe-'.$id,
            'event_fingerprint' => hash('sha256', 'pe-'.$id),
            'amount' => $amount,
            'currency' => 'VND',
            'status' => $status,
            'receiver_snapshot_ciphertext' => Crypt::encryptString(json_encode(['name' => 'Owner'])),
            'received_at' => now(),
            'credential_version' => 1,
        ]);
    }

    private function createAllocation(OwnerPaymentAttempt $attempt, OwnerPaymentReceipt $receipt, int $amount): OwnerPaymentAllocation
    {
        return OwnerPaymentAllocation::create([
            'organization_id' => $this->organization->id,
            'venue_id' => $this->venue->id,
            'receiving_connection_id' => $this->connection->id,
            'payment_attempt_id' => $attempt->id,
            'payment_receipt_id' => $receipt->id,
            'purpose' => $attempt->purpose,
            'purpose_id' => $attempt->purpose_id,
            'amount' => $amount,
            'currency' => 'VND',
            'credential_version' => 1,
            'receiver_snapshot_ciphertext' => $attempt->receiver_snapshot_ciphertext,
            'purpose_snapshot_ciphertext' => $attempt->purpose_snapshot_ciphertext,
            'allocated_at' => now(),
        ]);
    }

    private function createRefundObligation(OwnerPaymentAttempt $attempt, int $amount, string $status = OwnerRefundObligation::STATUS_ACKNOWLEDGED): OwnerRefundObligation
    {
        return OwnerRefundObligation::create([
            'organization_id' => $this->organization->id,
            'venue_id' => $this->venue->id,
            'payer_id' => $this->owner->id,
            'payment_attempt_id' => $attempt->id,
            'reference_code' => 'REFUND-'.uniqid(),
            'idempotency_key' => 'IDEM-REF-'.uniqid(),
            'request_fingerprint' => hash('sha256', uniqid()),
            'slot_ids' => [1],
            'gross_amount' => $amount,
            'fee_amount' => 0,
            'amount' => $amount,
            'currency' => 'VND',
            'status' => $status,
            'reason' => 'Schedule adjustment refund',
            'policy_snapshot' => ['type' => 'standard'],
            'recipient_ciphertext' => Crypt::encryptString(json_encode(['name' => 'Payer'])),
            'due_at' => now()->addDays(2),
            'requested_at' => now(),
            'requested_by' => $this->owner->id,
            'recipient_acknowledged_at' => now(),
        ]);
    }

    /**
     * Task 12.1 [D46]: Reconcile receipt/allocations across sources;
     * test that no individual mismatch is lost due to netting.
     */
    public function test_reconciliation_detects_mismatches_without_netting_opposite_variances_to_zero(): void
    {
        $reconciliationService = app(ReconciliationService::class);

        // Discrepancy 1: Allocation of 200,000 VND but receipt is only 100,000 VND (+100,000 variance)
        $attempt1 = $this->createAttempt('booking', 'BK-DISC-1', 200000);
        $receipt1 = $this->createReceipt($attempt1, 100000, OwnerPaymentReceipt::STATUS_ALLOCATED);
        $this->createAllocation($attempt1, $receipt1, 200000);

        // Discrepancy 2: Unallocated provider receipt of 100,000 VND (-100,000 variance)
        $this->createReceipt(null, 100000, OwnerPaymentReceipt::STATUS_UNMATCHED);

        // Run reconciliation
        $run = $reconciliationService->run($this->organization);

        // Assert: Netting (+100,000 + -100,000 = 0) MUST NOT zero the mismatch! Total absolute mismatch MUST be 200,000
        $this->assertSame('mismatch', $run->status);
        $this->assertSame(200000, $run->mismatch_amount);

        // Traceable FinanceException created with amount 200,000, severity high, due within 15 minutes
        $exception = FinanceException::query()
            ->where('organization_id', $this->organization->id)
            ->where('type', 'reconciliation_mismatch')
            ->where('status', 'open')
            ->first();

        $this->assertNotNull($exception);
        $this->assertSame(200000, $exception->amount);
        $this->assertSame('high', $exception->severity);
        $this->assertTrue($exception->due_at->greaterThanOrEqualTo(now()->addMinutes(14)));
        $this->assertTrue($exception->due_at->lessThanOrEqualTo(now()->addMinutes(16)));
        $this->assertSame($run->id, $exception->links['reconciliation_run_id'] ?? null);
    }

    /**
     * Task 12.2 [D47]: Note-only input does NOT zero mismatch, does NOT change run status
     * to 'matched', and does NOT claim auto-offsetting.
     */
    public function test_auto_resolve_note_only_does_not_zero_mismatch_or_mark_run_matched(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $autoResolveAction = app(AutoResolveReconciliation::class);

        // Create a mismatch scenario (+100,000 difference)
        $attempt = $this->createAttempt('booking', 'BK-NOTE-1', 150000);
        $receipt = $this->createReceipt($attempt, 50000, OwnerPaymentReceipt::STATUS_ALLOCATED);
        $this->createAllocation($attempt, $receipt, 150000);

        $run = $reconciliationService->run($this->organization);
        $this->assertSame('mismatch', $run->status);
        $this->assertSame(100000, $run->mismatch_amount);

        $exception = FinanceException::where('organization_id', $this->organization->id)
            ->where('type', 'reconciliation_mismatch')
            ->where('status', 'open')
            ->firstOrFail();

        // 1. Attempt auto-resolve via HTTP endpoint with only notes (no evidence_reference)
        $response = $this->actingAs($this->owner)->postJson('/api/finance/reconciliation/auto-resolve', [
            'run_id' => $run->id,
            'notes' => 'Yêu cầu bù trừ tự động bằng ghi chú của kế toán',
        ], ['X-Organization-Id' => $this->organization->id]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['evidence_reference']);

        // 2. Attempt auto-resolve via Action directly with note-only DTO
        $noteOnlyData = new AutoResolveReconciliationData(
            runId: $run->id,
            scope: 'all',
            notes: 'Chỉ có ghi chú, không có evidence reference',
            evidenceReference: null
        );

        try {
            $autoResolveAction->execute($this->organization, $noteOnlyData, $this->owner);
            $this->fail('Expected ValidationException was not thrown for note-only resolution.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('evidence_reference', $e->errors());
            $this->assertStringContainsString('Cannot auto-resolve reconciliation mismatch without verified correction evidence', $e->getMessage());
        }

        // Assert: Exception MUST remain open, run status MUST remain mismatch, mismatch_amount MUST NOT be zeroed
        $this->assertSame('open', $exception->fresh()->status);
        $this->assertSame('mismatch', $run->fresh()->status);
        $this->assertSame(100000, $run->fresh()->mismatch_amount);
        $this->assertGreaterThan(0, $run->fresh()->mismatch_amount);
    }

    /**
     * Task 12.2 [D47]: Require verified evidence/correction and auditable rerun before matched;
     * test auto-resolve resolves when mismatch is cleared.
     */
    public function test_auto_resolve_with_verified_evidence_and_rerun_resolves_when_mismatch_is_cleared(): void
    {
        $reconciliationService = app(ReconciliationService::class);
        $autoResolveAction = app(AutoResolveReconciliation::class);

        // Initial state: allocation of 100,000 VND but receipt is unallocated (pending)
        $attempt = $this->createAttempt('booking', 'BK-RESOLVE-1', 100000);
        $receipt = $this->createReceipt($attempt, 100000, OwnerPaymentReceipt::STATUS_PENDING);
        $this->createAllocation($attempt, $receipt, 100000);

        $run = $reconciliationService->run($this->organization);
        $this->assertSame('mismatch', $run->status);

        $exception = FinanceException::where('organization_id', $this->organization->id)
            ->where('type', 'reconciliation_mismatch')
            ->where('status', 'open')
            ->firstOrFail();

        // Step 1: Provide evidence reference, BUT data is NOT yet fixed.
        // Auditable rerun will still detect mismatch, so exception remains open.
        $prematureData = new AutoResolveReconciliationData(
            runId: $run->id,
            scope: 'all',
            notes: 'Thử nghiệm khi chưa sửa dữ liệu',
            evidenceReference: 'EVID-MBBANK-STMT-999',
            correctionType: 'bank_statement_verification'
        );

        $prematureResult = $autoResolveAction->execute($this->organization, $prematureData, $this->owner);
        $this->assertFalse($prematureResult['success']);
        $this->assertSame(0, $prematureResult['resolved_count']);
        $this->assertSame('open', $exception->fresh()->status);
        $this->assertSame('mismatch', $run->fresh()->status);

        // Step 2: Fix the underlying data (provider receipt verified & allocated)
        $receipt->update(['status' => OwnerPaymentReceipt::STATUS_ALLOCATED]);

        // Step 3: Trigger autoResolve with verified evidence reference
        $validData = new AutoResolveReconciliationData(
            runId: $run->id,
            scope: 'all',
            notes: 'Đã xác minh sao kê ngân hàng đối chiếu giao dịch',
            evidenceReference: 'EVID-MBBANK-STMT-999',
            correctionType: 'bank_statement_verification',
            correctionDetails: ['statement_id' => 'STMT-999', 'verified_amount' => 100000]
        );

        $result = $autoResolveAction->execute($this->organization, $validData, $this->owner);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['resolved_count']);

        // Assert: Rerun executed, run status is now 'matched', mismatch_amount is 0
        $this->assertSame('matched', $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->mismatch_amount);

        // Assert: Exception is cleanly resolved with audit trail
        $resolvedException = $exception->fresh();
        $this->assertSame('resolved', $resolvedException->status);
        $this->assertSame($this->owner->id, $resolvedException->resolved_by);
        $this->assertNotNull($resolvedException->resolved_at);
        $this->assertStringContainsString('EVID-MBBANK-STMT-999', $resolvedException->resolution);
        $this->assertSame('EVID-MBBANK-STMT-999', $resolvedException->metadata['evidence_reference'] ?? null);
    }

    /**
     * D47 Manual Path Bypass Gate:
     * POST /api/finance/exceptions/{id}/resolve with note-only on reconciliation_mismatch fails.
     * POST /api/finance/exceptions/{id}/resolve with evidence and cleared data triggers rerun and successfully resolves.
     */
    public function test_manual_resolve_endpoint_rejects_note_only_for_reconciliation_mismatch_and_requires_evidence_and_rerun(): void
    {
        $reconciliationService = app(ReconciliationService::class);

        // Initial state: allocation of 100,000 VND but receipt is unallocated (pending)
        $attempt = $this->createAttempt('booking', 'BK-MANUAL-GATE-1', 100000);
        $receipt = $this->createReceipt($attempt, 100000, OwnerPaymentReceipt::STATUS_PENDING);
        $this->createAllocation($attempt, $receipt, 100000);

        $run = $reconciliationService->run($this->organization);
        $this->assertSame('mismatch', $run->status);

        $exception = FinanceException::where('organization_id', $this->organization->id)
            ->where('type', 'reconciliation_mismatch')
            ->where('status', 'open')
            ->firstOrFail();

        $headers = [
            'X-Organization-Id' => $this->organization->id,
            'Idempotency-Key' => 'manual-gate-'.uniqid(),
        ];

        // 1. Attempt note-only resolution (cannot bypass D47)
        $noteOnlyResponse = $this->actingAs($this->owner)->postJson(
            "/api/finance/exceptions/{$exception->id}/resolve",
            [
                'action_type' => 'manual_override',
                'reason' => 'Ghi chú giải trình kiểm toán thủ công không kèm chứng từ',
                'note' => 'Cố gắng bypass D47 bằng note thủ công',
            ],
            $headers
        );

        $noteOnlyResponse->assertStatus(422);
        $noteOnlyResponse->assertJsonValidationErrors(['evidence_reference']);
        $this->assertSame('open', $exception->fresh()->status);
        $this->assertSame('mismatch', $run->fresh()->status);

        // 2. Attempt resolution with evidence reference, BUT underlying mismatch is not cleared yet
        $prematureResponse = $this->actingAs($this->owner)->postJson(
            "/api/finance/exceptions/{$exception->id}/resolve",
            [
                'action_type' => 'manual_override',
                'reason' => 'Giải trình kiểm toán thủ công kèm chứng từ nhưng chưa sửa dữ liệu',
                'evidence_reference' => 'EVID-MANUAL-GATE-999',
            ],
            array_merge($headers, ['Idempotency-Key' => 'manual-gate-'.uniqid()])
        );

        $prematureResponse->assertStatus(422);
        $prematureResponse->assertJsonValidationErrors(['rerun']);
        $this->assertSame('open', $exception->fresh()->status);
        $this->assertSame('mismatch', $run->fresh()->status);

        // 3. Fix the underlying data (provider receipt verified & allocated)
        $receipt->update(['status' => OwnerPaymentReceipt::STATUS_ALLOCATED]);

        // 4. Resolve with evidence and cleared data triggers rerun and successfully resolves
        $successResponse = $this->actingAs($this->owner)->postJson(
            "/api/finance/exceptions/{$exception->id}/resolve",
            [
                'action_type' => 'manual_override',
                'reason' => 'Đã xác minh đối chiếu sao kê ngân hàng và phân bổ hoàn tất',
                'evidence_reference' => 'EVID-MANUAL-GATE-999',
            ],
            array_merge($headers, ['Idempotency-Key' => 'manual-gate-'.uniqid()])
        );

        $successResponse->assertOk();
        $successResponse->assertJsonPath('exception.status', 'resolved');

        // Assert: Run status is matched, mismatch_amount is 0
        $this->assertSame('matched', $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->mismatch_amount);

        // Assert: Exception is cleanly resolved with audit trail and evidence reference
        $resolvedException = $exception->fresh();
        $this->assertSame('resolved', $resolvedException->status);
        $this->assertSame($this->owner->id, $resolvedException->resolved_by);
        $this->assertNotNull($resolvedException->resolved_at);
        $this->assertSame('EVID-MANUAL-GATE-999', $resolvedException->metadata['evidence_reference'] ?? null);
    }

    /**
     * Task 12.1 [D46]: Reconcile across direct owner receipts, split shares, recurring invoices,
     * POS orders, refunds, and platform subscriptions.
     */
    public function test_reconciles_across_direct_owner_split_recurring_pos_and_platform_subscriptions(): void
    {
        $reconciliationService = app(ReconciliationService::class);

        // 1. Direct Owner Booking
        $attemptBooking = $this->createAttempt('booking', 'BK-100', 250000);
        $rcptBooking = $this->createReceipt($attemptBooking, 250000);
        $this->createAllocation($attemptBooking, $rcptBooking, 250000);

        // 2. Direct Owner Split Share
        $attemptSplit = $this->createAttempt('split_share', 'split_group:1:share:1', 150000);
        $rcptSplit = $this->createReceipt($attemptSplit, 150000);
        $this->createAllocation($attemptSplit, $rcptSplit, 150000);

        // 3. Direct Owner Recurring Invoice
        $attemptRecurring = $this->createAttempt('recurring_invoice', 'INV-2026-09', 500000);
        $rcptRecurring = $this->createReceipt($attemptRecurring, 500000);
        $this->createAllocation($attemptRecurring, $rcptRecurring, 500000);

        // 4. Direct Owner POS Order
        $attemptPos = $this->createAttempt('pos_order', 'POS-ORD-001', 80000);
        $rcptPos = $this->createReceipt($attemptPos, 80000);
        $this->createAllocation($attemptPos, $rcptPos, 80000);

        // 5. Direct Owner Compensating Refund
        $this->createRefundObligation($attemptBooking, 50000);

        // 6. Platform Subscription
        $platformAttempt = PlatformPaymentAttempt::create([
            'organization_id' => $this->organization->id,
            'reference_code' => 'PLAT-SUB-99',
            'amount' => 300000,
            'currency' => 'VND',
            'provider' => 'payos',
            'provider_ref' => 'payos-sub-99',
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);
        PlatformProviderReceipt::create([
            'payment_attempt_id' => $platformAttempt->id,
            'provider' => 'payos',
            'provider_transaction_id' => 'tx-sub-99',
            'amount' => 300000,
            'currency' => 'VND',
            'raw_payload' => ['status' => 'PAID'],
            'verified_at' => now(),
        ]);

        // Run reconciliation
        $run = $reconciliationService->run($this->organization);

        // Assert: Everything matches cleanly
        $this->assertSame('matched', $run->status);
        $this->assertSame(0, $run->mismatch_amount);

        // Verify accurate breakdown by source classification
        $breakdown = $run->details['breakdown'];
        $this->assertSame(250000, $breakdown['direct_owner']['bookings']['amount']);
        $this->assertSame(1, $breakdown['direct_owner']['bookings']['count']);

        $this->assertSame(150000, $breakdown['direct_owner']['split_shares']['amount']);
        $this->assertSame(1, $breakdown['direct_owner']['split_shares']['count']);

        $this->assertSame(500000, $breakdown['direct_owner']['recurring_invoices']['amount']);
        $this->assertSame(1, $breakdown['direct_owner']['recurring_invoices']['count']);

        $this->assertSame(80000, $breakdown['direct_owner']['pos_orders']['amount']);
        $this->assertSame(1, $breakdown['direct_owner']['pos_orders']['count']);

        $this->assertSame(50000, $breakdown['direct_owner']['refunds']['amount']);
        $this->assertSame(1, $breakdown['direct_owner']['refunds']['count']);

        $this->assertSame(300000, $breakdown['platform']['subscriptions']['amount']);
        $this->assertSame(1, $breakdown['platform']['subscriptions']['count']);

        // Verify receivers breakdown correctly separates direct owner and platform
        $this->assertArrayHasKey("connection:{$this->connection->id}", $run->details['receivers']);
        $this->assertArrayHasKey('platform', $run->details['receivers']);
        $this->assertSame('direct_owner', $run->details['receivers']["connection:{$this->connection->id}"]['entity']);
        $this->assertSame('platform', $run->details['receivers']['platform']['entity']);
    }
}
