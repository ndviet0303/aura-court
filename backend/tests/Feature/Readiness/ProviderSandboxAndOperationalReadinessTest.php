<?php

namespace Tests\Feature\Readiness;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Court;
use App\Models\FinanceException;
use App\Models\Journal;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundObligation;
use App\Models\PhaseGateReview;
use App\Models\PlatformPaymentAttempt;
use App\Models\PlatformPlan;
use App\Models\PlatformProviderReceipt;
use App\Models\PlatformSubscription;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\Infrastructure\MoneyFlowUatService;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Task 13.5 Acceptance Test:
 * Provider sandbox end-to-end for owner receipt and platform subscription,
 * manual setup/secret rotation/late receipt/refund evidence; missing capabilities
 * are explicitly recorded as blocked/manual and never marked production-ready.
 *
 * Operational Scenarios:
 * 1. Owner receipt sandbox flow: Authenticated SePay webhook event received, verified against connection,
 *    creates OwnerPaymentReceipt with status 'allocated' and sets OwnerPaymentAttempt to STATUS_PAID.
 * 2. Platform subscription sandbox flow: PlatformPaymentAttempt created, verified platform provider event
 *    confirms payment, activates subscription and extends period on renewal.
 * 3. Secret rotation & Credential update: Rotate webhook secret on ReceivingConnection; webhook using
 *    old secret is rejected with 401/403, webhook using new secret succeeds with 200.
 * 4. Late receipt handling: Receipt arriving for an expired attempt does not mark expired booking as paid;
 *    records receipt with status 'late' / 'unmatched', preventing corrupt state.
 * 5. Refund evidence verification: Owner refund obligation cannot be marked acknowledged without external
 *    proof (bank transaction reference / ciphertext / proof).
 * 6. Documenting blocked capabilities: Automated outbound bank disbursement (direct bank transfer API) is
 *    verified as NOT automated / manual-only; recorded as blocked/manual without claiming automated readiness.
 */
class ProviderSandboxAndOperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Scenario 1: Owner receipt sandbox flow.
     * Authenticated SePay webhook event received, verified against connection,
     * creates OwnerPaymentReceipt with status 'allocated' and sets OwnerPaymentAttempt to STATUS_PAID.
     */
    public function test_owner_receipt_sandbox_flow_creates_allocated_receipt_and_marks_attempt_paid(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $organization = $fixture['org1']['org'];
        $venue = $fixture['org1']['venue1'];
        $court = $fixture['org1']['court1'];

        Sanctum::actingAs($owner);
        config([
            'features.flags.direct_owner_payments' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        [$connection, $secret] = $this->createVerifiedReceivingConnection($venue, '0987654321');

        $reservation = $this->createDirectReservation($owner->id, $organization->id, $court->id, 'RSV-SANDBOX-001', 120000);

        // Initiate payment attempt
        $paymentResponse = $this->postJson("/api/reservations/{$reservation->code}/payment")
            ->assertCreated();

        $attemptData = $paymentResponse->json('payment_attempt');
        $this->assertSame(OwnerPaymentAttempt::STATUS_PENDING, $attemptData['status']);
        $this->assertSame(120000, $attemptData['amount']);
        $this->assertSame('0987654321', $attemptData['receiver']['account_number']);

        $attempt = OwnerPaymentAttempt::findOrFail((int) $attemptData['id']);
        $this->assertSame($attemptData['reference'], $attempt->reference);

        // Deliver signed SePay webhook event matching attempt
        $eventId = 'sepay-sandbox-evt-'.Str::uuid();
        $eventPayload = [
            'id' => $eventId,
            'transactionDate' => now()->toIso8601String(),
            'accountNumber' => '0987654321',
            'code' => $attempt->reference,
            'content' => $attempt->reference.' Aura Court sandbox payment',
            'transferType' => 'in',
            'transferAmount' => 120000,
            'referenceCode' => 'MB-SANDBOX-TXN-'.Str::upper(Str::random(8)),
            'currency' => 'VND',
        ];

        $webhookResponse = $this->sendSePayWebhook($connection, $secret, $eventPayload)
            ->assertOk()
            ->assertExactJson(['success' => true]);

        // Verify receipt
        $receipt = OwnerPaymentReceipt::where('provider_event_id', $eventId)->firstOrFail();
        $this->assertSame(OwnerPaymentReceipt::STATUS_ALLOCATED, $receipt->status);
        $this->assertSame(120000, (int) $receipt->amount);
        $this->assertSame('VND', $receipt->currency);
        $this->assertNull($receipt->exception_code);
        $this->assertNotNull($receipt->processed_at);
        $this->assertSame($attempt->id, $receipt->payment_attempt_id);

        // Verify attempt is marked STATUS_PAID
        $attempt->refresh();
        $this->assertSame(OwnerPaymentAttempt::STATUS_PAID, $attempt->status);
        $this->assertNotNull($attempt->paid_at);

        // Verify allocation created
        $allocation = OwnerPaymentAllocation::where('payment_receipt_id', $receipt->id)->firstOrFail();
        $this->assertSame($attempt->id, $allocation->payment_attempt_id);
        $this->assertSame(120000, (int) $allocation->amount);
        $this->assertSame('booking', $allocation->purpose);

        // Verify reservation confirmed
        $reservation->refresh();
        $this->assertSame('confirmed', $reservation->status);
        $this->assertNotNull($reservation->confirmed_at);
        $this->assertSame(0, $reservation->slots()->where('status', '!=', 'confirmed')->count());
        $this->assertSame(0, $reservation->entitlements()->where('status', '!=', 'active')->count());

        // Verify financial journal entry posted
        $journal = Journal::where('event_type', 'owner.direct_receipt')
            ->where('event_id', "owner-receipt:{$receipt->id}")
            ->firstOrFail();
        $this->assertSame('owner', $journal->ledger_entity);
        $this->assertSame('direct_owner_v1', $journal->payment_model);
        $this->assertSame(120000, (int) $journal->total_credit);

        // Idempotency check: Replay of identical webhook allocates once
        $this->sendSePayWebhook($connection, $secret, $eventPayload)
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertSame(1, OwnerPaymentAllocation::where('payment_receipt_id', $receipt->id)->count());
        $this->assertSame(1, Journal::where('event_type', 'owner.direct_receipt')->where('event_id', "owner-receipt:{$receipt->id}")->count());
    }

    /**
     * Scenario 2: Platform subscription sandbox flow.
     * PlatformPaymentAttempt created, verified platform provider event confirms payment,
     * activates subscription and extends period on renewal.
     */
    public function test_platform_subscription_sandbox_flow_confirms_payment_activates_subscription_and_extends_period(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $org = Organization::create([
            'name' => 'Sandbox Subscriptions Club',
            'slug' => 'sandbox-subs-club-'.Str::random(6),
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
            'code' => 'PRO_MONTHLY',
            'name' => 'Gói Pro Tháng',
            'version' => 1,
            'price' => 600000,
            'currency' => 'VND',
            'billing_cycle' => 'monthly',
            'terms_version' => '2026.SUB_PRO',
            'terms_text' => 'Điều khoản sử dụng nền tảng',
            'is_active' => true,
            'max_venues' => 5,
            'max_courts' => 20,
            'max_staff' => 10,
        ]);

        $checksumKey = 'sandbox-payos-checksum-key-'.Str::random(16);
        config(['services.payos.checksum_key' => $checksumKey]);

        // 1. Initial subscription payment initiation
        $reference1 = 'SUB-SANDBOX-001';
        $attempt1 = PlatformPaymentAttempt::create([
            'organization_id' => $org->id,
            'reference_code' => $reference1,
            'amount' => 600000,
            'currency' => 'VND',
            'provider' => 'payos',
            'provider_ref' => 'PAYOS-INTENT-SB-001',
            'status' => 'pending',
            'metadata' => [
                'plan_id' => $plan->id,
                'plan_version' => 1,
                'billing_cycle' => 'monthly',
                'billing_months' => 1,
                'accepted_terms_version' => '2026.SUB_PRO',
                'actor_id' => $owner->id,
            ],
        ]);

        $payosTxn1 = 'TXN-PAYOS-SB-001';
        $data1 = [
            'orderCode' => $reference1,
            'amount' => 600000,
            'description' => 'Platform subscription initial',
            'accountNumber' => '1122334455',
            'reference' => $payosTxn1,
            'transactionDateTime' => now()->toIso8601String(),
            'currency' => 'VND',
            'paymentLinkId' => $payosTxn1,
            'code' => '00',
        ];

        $payload1 = [
            'id' => 'payos-evt-sub-001',
            'code' => '00',
            'desc' => 'success',
            'success' => true,
            'data' => $data1,
            'signature' => $this->payosSignature($data1, $checksumKey),
        ];

        $rawBody1 = json_encode($payload1, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/webhooks/payos', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $payload1['signature'],
        ], $rawBody1)->assertOk();

        // Verify payment attempt 1 succeeded
        $attempt1->refresh();
        $this->assertSame('succeeded', $attempt1->status);
        $this->assertNotNull($attempt1->paid_at);
        $this->assertNotNull($attempt1->subscription_id);

        // Verify PlatformProviderReceipt created
        $receipt1 = PlatformProviderReceipt::where('payment_attempt_id', $attempt1->id)->firstOrFail();
        $this->assertSame($payosTxn1, $receipt1->provider_transaction_id);
        $this->assertSame(600000, (int) $receipt1->amount);
        $this->assertSame('VND', $receipt1->currency);
        $this->assertNotNull($receipt1->verified_at);

        // Verify Subscription active
        $subscription = PlatformSubscription::where('organization_id', $org->id)->firstOrFail();
        $this->assertSame('active', $subscription->status);
        $this->assertTrue($subscription->isValid());
        $initialEndsAt = $subscription->ends_at->copy();
        $this->assertTrue($initialEndsAt->isFuture());

        // 2. Renewal flow: PlatformPaymentAttempt created, verified platform event confirms renewal & extends period
        $reference2 = 'SUB-SANDBOX-002';
        $attempt2 = PlatformPaymentAttempt::create([
            'organization_id' => $org->id,
            'reference_code' => $reference2,
            'amount' => 600000,
            'currency' => 'VND',
            'provider' => 'payos',
            'provider_ref' => 'PAYOS-INTENT-SB-002',
            'status' => 'pending',
            'metadata' => [
                'plan_id' => $plan->id,
                'plan_version' => 1,
                'billing_cycle' => 'monthly',
                'billing_months' => 1,
                'accepted_terms_version' => '2026.SUB_PRO',
                'actor_id' => $owner->id,
            ],
        ]);

        $payosTxn2 = 'TXN-PAYOS-SB-002';
        $data2 = [
            'orderCode' => $reference2,
            'amount' => 600000,
            'description' => 'Platform subscription renewal',
            'accountNumber' => '1122334455',
            'reference' => $payosTxn2,
            'transactionDateTime' => now()->toIso8601String(),
            'currency' => 'VND',
            'paymentLinkId' => $payosTxn2,
            'code' => '00',
        ];

        $payload2 = [
            'id' => 'payos-evt-sub-002',
            'code' => '00',
            'desc' => 'success',
            'success' => true,
            'data' => $data2,
            'signature' => $this->payosSignature($data2, $checksumKey),
        ];

        $rawBody2 = json_encode($payload2, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/webhooks/payos', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $payload2['signature'],
        ], $rawBody2)->assertOk();

        $attempt2->refresh();
        $this->assertSame('succeeded', $attempt2->status);

        // Verify period was extended by 1 month beyond the initial end date
        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $expectedExtendedEnd = $initialEndsAt->copy()->addMonthsNoOverflow(1, $initialEndsAt->day);
        $this->assertSame($expectedExtendedEnd->toDateString(), $subscription->ends_at->toDateString());

        // Verify duplicate confirmation event is idempotent and does not extend duration twice
        $this->call('POST', '/api/webhooks/payos', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $payload2['signature'],
        ], $rawBody2)->assertOk();

        $subscription->refresh();
        $this->assertSame($expectedExtendedEnd->toDateString(), $subscription->ends_at->toDateString());
    }

    /**
     * Scenario 3: Secret rotation & Credential update.
     * Rotate webhook secret or API token on ReceivingConnection.
     * Webhook using old secret is rejected with 401/403.
     * Webhook using new secret succeeds with 200.
     */
    public function test_secret_rotation_and_credential_update_rejects_old_secret_and_accepts_new_secret(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $venue = $fixture['org1']['venue1'];

        Sanctum::actingAs($owner);

        // Initial setup with secret V1
        $initialSecret = 'whsec_v1_'.Str::random(40);
        $created = $this->postJson("/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/receiving-connection", [
            'bank_code' => 'MBBank',
            'account_name' => 'Venue Owner V1',
            'account_number' => '1234500001',
            'legal_owner_confirmed' => true,
        ])->assertCreated();

        $connectionId = (int) $created->json('data.id');
        $connection = ReceivingConnection::findOrFail($connectionId);

        $this->postJson("/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/receiving-connection/{$connectionId}/credential", [
            'webhook_api_key' => $initialSecret,
        ])->assertOk()->assertJsonPath('data.credential_version', 1);

        // Complete initial verification
        $verifyPayload = [
            'id' => 'verify-v1-'.Str::uuid(),
            'transactionDate' => now()->toIso8601String(),
            'accountNumber' => '1234500001',
            'transferType' => 'in',
            'transferAmount' => 2000,
            'referenceCode' => 'VERIFY-V1',
        ];
        $this->sendSePayWebhook($connection, $initialSecret, $verifyPayload)->assertOk();
        $this->postJson("/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/receiving-connection/{$connectionId}/select")->assertOk();

        $connection->refresh();
        $this->assertSame(ReceivingConnection::STATUS_VERIFIED, $connection->status);
        $this->assertSame(1, $connection->credential_version);

        // Owner rotates secret to Secret V2
        $rotatedSecret = 'whsec_v2_'.Str::random(40);
        $rotateResponse = $this->postJson("/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/receiving-connection/{$connectionId}/credential", [
            'webhook_api_key' => $rotatedSecret,
        ])->assertOk();

        $rotateResponse->assertJsonPath('data.credential_version', 2)
            ->assertJsonPath('data.status', ReceivingConnection::STATUS_PENDING_VERIFICATION)
            ->assertJsonMissingPath('data.webhook_secret')
            ->assertDontSee($rotatedSecret);

        $connection->refresh();
        $this->assertSame(2, $connection->credential_version);
        $this->assertSame($rotatedSecret, Crypt::decryptString($connection->webhook_secret_ciphertext));

        $operationalPayload = [
            'id' => 'rotation-test-evt-'.Str::uuid(),
            'transactionDate' => now()->toIso8601String(),
            'accountNumber' => '1234500001',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'ROTATION-TXN-01',
            'code' => 'AC-ROTATION-01',
        ];

        // Webhook using OLD secret is rejected with 401 Unauthorized
        $oldSecretResponse = $this->sendSePayWebhook($connection, $initialSecret, $operationalPayload);
        $this->assertTrue(in_array($oldSecretResponse->status(), [401, 403], true));
        $this->assertDatabaseMissing('owner_payment_receipts', ['provider_event_id' => $operationalPayload['id']]);

        // Webhook using NEW secret succeeds with 200 OK
        $newSecretResponse = $this->sendSePayWebhook($connection, $rotatedSecret, $operationalPayload);
        $newSecretResponse->assertOk()->assertExactJson(['success' => true]);

        $this->assertDatabaseHas('owner_payment_receipts', [
            'provider_event_id' => $operationalPayload['id'],
            'credential_version' => 2,
        ]);

        // Connection transitions to verified on authenticated event
        $connection->refresh();
        $this->assertSame(ReceivingConnection::STATUS_VERIFIED, $connection->status);
    }

    /**
     * Scenario 4: Late receipt handling.
     * When a receipt arrives for an expired attempt, it does not mark the expired booking as paid;
     * records receipt with status 'late' / 'unmatched', preventing corrupt state.
     */
    public function test_late_receipt_handling_on_expired_attempt_prevents_corrupt_state(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $organization = $fixture['org1']['org'];
        $venue = $fixture['org1']['venue1'];
        $court = $fixture['org1']['court1'];

        Sanctum::actingAs($owner);
        config([
            'features.flags.direct_owner_payments' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        [$connection, $secret] = $this->createVerifiedReceivingConnection($venue, '0901234567');

        // Part A: Late receipt after attempt expiry
        $reservation = $this->createDirectReservation($owner->id, $organization->id, $court->id, 'RSV-EXPIRED-LATE', 150000);

        $attempt = app(OwnerPaymentAttemptService::class)->createAttempt(
            $connection,
            $owner,
            'booking',
            $reservation->code,
            'AC-LATE-RECEIPT-01',
            150000,
            'VND',
            now()->addMinutes(10),
            'checkout-late-01'
        );

        // Transition reservation and attempt to expired
        $reservation->update(['hold_expires_at' => now()->subMinutes(15), 'status' => 'expired']);
        $attempt->update(['expires_at' => now()->subMinutes(15), 'status' => OwnerPaymentAttempt::STATUS_EXPIRED]);
        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, $attempt->fresh()->status);

        // Late receipt event arrives with transaction date after expiry
        $lateEventId = 'late-receipt-evt-'.Str::uuid();
        $latePayload = [
            'id' => $lateEventId,
            'transactionDate' => now()->toIso8601String(), // After attempt's expires_at
            'accountNumber' => '0901234567',
            'code' => 'AC-LATE-RECEIPT-01',
            'content' => 'AC-LATE-RECEIPT-01 Late transfer',
            'transferType' => 'in',
            'transferAmount' => 150000,
            'referenceCode' => 'BANK-LATE-01',
            'currency' => 'VND',
        ];

        $this->sendSePayWebhook($connection, $secret, $latePayload)->assertOk();

        $receipt = OwnerPaymentReceipt::where('provider_event_id', $lateEventId)->firstOrFail();

        // Must record status as 'late' (or exception) and NOT 'allocated'
        $this->assertContains($receipt->status, [OwnerPaymentReceipt::STATUS_LATE, OwnerPaymentReceipt::STATUS_EXCEPTION]);
        $this->assertNotSame(OwnerPaymentReceipt::STATUS_ALLOCATED, $receipt->status);

        // Booking remains expired, NEVER flipped to confirmed
        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, $attempt->fresh()->status);
        $this->assertDatabaseMissing('owner_payment_allocations', ['payment_receipt_id' => $receipt->id]);

        // FinanceException recorded for late transfer reconciliation
        $this->assertDatabaseHas('finance_exceptions', [
            'reference' => 'owner-receipt:'.$receipt->id,
            'status' => 'open',
        ]);

        // Part B: Unmatched receipt (code does not match any pending attempt)
        $unmatchedEventId = 'unmatched-receipt-evt-'.Str::uuid();
        $unmatchedPayload = [
            'id' => $unmatchedEventId,
            'transactionDate' => now()->toIso8601String(),
            'accountNumber' => '0901234567',
            'code' => 'UNKNOWN-NONEXISTENT-CODE',
            'transferType' => 'in',
            'transferAmount' => 200000,
            'referenceCode' => 'BANK-UNMATCHED-01',
            'currency' => 'VND',
        ];

        $this->sendSePayWebhook($connection, $secret, $unmatchedPayload)->assertOk();

        $unmatchedReceipt = OwnerPaymentReceipt::where('provider_event_id', $unmatchedEventId)->firstOrFail();
        $this->assertSame(OwnerPaymentReceipt::STATUS_UNMATCHED, $unmatchedReceipt->status);
        $this->assertNull($unmatchedReceipt->payment_attempt_id);
        $this->assertDatabaseMissing('owner_payment_allocations', ['payment_receipt_id' => $unmatchedReceipt->id]);
        $this->assertDatabaseHas('finance_exceptions', [
            'type' => 'owner_payment_unmatched_reference',
            'reference' => 'owner-receipt:'.$unmatchedReceipt->id,
            'status' => 'open',
        ]);
    }

    /**
     * Scenario 5: Refund evidence verification.
     * Owner refund obligation cannot be marked acknowledged without external proof
     * (bank transaction reference / ciphertext / proof).
     */
    public function test_refund_evidence_verification_requires_external_proof_before_acknowledgment(): void
    {
        $fixture = $this->createPaidDirectReservationFixture();
        $owner = $fixture['owner'];
        $payer = $fixture['payer'];
        $reservation = $fixture['reservation'];
        $slot = $fixture['slots']->first();

        // 1. Payer creates refund request
        Sanctum::actingAs($payer);
        $requestPayload = [
            'slot_ids' => [$slot->id],
            'reason' => 'Customer schedule change refund',
            'idempotency_key' => 'refund-e2e-evidence-01',
            'recipient' => [
                'bank_code' => 'MBBank',
                'account_name' => 'Nguyen Van Payer',
                'account_number' => '123456789012',
            ],
        ];

        $refundCreated = $this->withHeader('X-Organization-ID', (string) $reservation->organization_id)
            ->postJson("/api/reservations/{$reservation->code}/refund-requests", $requestPayload)
            ->assertCreated();

        $refundId = (int) $refundCreated->json('data.id');
        $obligation = OwnerRefundObligation::findOrFail($refundId);
        $this->assertSame(OwnerRefundObligation::STATUS_REQUESTED, $obligation->status);

        // 2. Owner approves the refund obligation
        Sanctum::actingAs($owner);
        $this->withHeader('X-Organization-ID', (string) $reservation->organization_id)
            ->postJson("/api/refund-requests/{$refundId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', OwnerRefundObligation::STATUS_APPROVED);

        $obligation->refresh();
        $this->assertSame(OwnerRefundObligation::STATUS_APPROVED, $obligation->status);

        // Negative check 5a: Payer tries to acknowledge BEFORE owner reports external transfer
        Sanctum::actingAs($payer);
        $this->withHeader('X-Organization-ID', (string) $reservation->organization_id)
            ->postJson("/api/refund-requests/{$refundId}/acknowledge", ['accepted' => true])
            ->assertStatus(409); // Conflict: Only reported owner transfer can be acknowledged

        $this->assertSame(OwnerRefundObligation::STATUS_APPROVED, $obligation->fresh()->status);

        // Negative check 5b: Owner attempts to report execution with MISSING external proof / reference
        Sanctum::actingAs($owner);
        $this->withHeader('X-Organization-ID', (string) $reservation->organization_id)
            ->postJson("/api/refund-requests/{$refundId}/report-execution", [
                'transfer_reference' => '', // Empty proof
                'evidence' => '',
            ])
            ->assertStatus(422);

        $this->assertSame(OwnerRefundObligation::STATUS_APPROVED, $obligation->fresh()->status);

        // 3. Owner reports valid external bank transfer proof
        $bankTransferRef = 'FT26270'.Str::upper(Str::random(10));
        $proofDescription = 'Chuyển khoản qua ngân hàng MBBank tham chiếu '.$bankTransferRef;

        $reportResponse = $this->withHeader('X-Organization-ID', (string) $reservation->organization_id)
            ->postJson("/api/refund-requests/{$refundId}/report-execution", [
                'transfer_reference' => $bankTransferRef,
                'evidence' => $proofDescription,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OwnerRefundObligation::STATUS_OWNER_REPORTED)
            ->assertJsonPath('data.execution_reference', $bankTransferRef);

        $obligation->refresh();
        $this->assertSame(OwnerRefundObligation::STATUS_OWNER_REPORTED, $obligation->status);
        $this->assertSame($bankTransferRef, $obligation->execution_reference);
        $this->assertNotNull($obligation->execution_evidence_ciphertext);
        $decryptedEvidence = json_decode(Crypt::decryptString($obligation->execution_evidence_ciphertext), true);
        $this->assertSame($bankTransferRef, $decryptedEvidence['transfer_reference']);
        $this->assertSame($proofDescription, $decryptedEvidence['evidence']);

        // 4. Now payer acknowledges with external proof verified
        Sanctum::actingAs($payer);
        $ackResponse = $this->withHeader('X-Organization-ID', (string) $reservation->organization_id)
            ->postJson("/api/refund-requests/{$refundId}/acknowledge", [
                'accepted' => true,
                'note' => 'Received bank refund in full',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OwnerRefundObligation::STATUS_ACKNOWLEDGED);

        $obligation->refresh();
        $this->assertSame(OwnerRefundObligation::STATUS_ACKNOWLEDGED, $obligation->status);
        $this->assertNotNull($obligation->recipient_acknowledged_at);

        // Assert direct owner refund journal posted and zero platform custody
        $journal = Journal::where('event_type', 'owner.direct_refund_acknowledged')
            ->where('event_id', 'owner-refund:'.$obligation->id)
            ->firstOrFail();
        $this->assertSame('owner', $journal->ledger_entity);
        $this->assertSame('direct_owner_v1', $journal->payment_model);
        $this->assertSame((int) $obligation->amount, (int) $journal->total_debit);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    /**
     * Scenario 6: Documenting blocked capabilities.
     * Automated outbound bank disbursement (direct bank transfer API) is verified as NOT automated / manual-only.
     * The system records it as blocked/manual, and does not claim automated disbursement readiness.
     */
    public function test_automated_outbound_bank_disbursement_is_blocked_and_documented_as_manual_only(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $org = $fixture['org1']['org'];
        $owner = $fixture['org1']['owner'];
        $venue = $fixture['org1']['venue1'];

        Sanctum::actingAs($owner);
        [$connection] = $this->createVerifiedReceivingConnection($venue, '0123456789');

        // Verify receiving connection provider is sepay
        $this->assertSame('sepay', $connection->provider);

        // 1. Verify direct owner payment provider has NO automated disbursement / payout capability
        // SePay is strictly an inbound payment and webhook notifications provider, not an automated outbound debit gateway.
        $this->assertFalse(method_exists($connection, 'submitAutomatedDisbursement'));
        $this->assertFalse(method_exists(app(OwnerPaymentAttemptService::class), 'submitAutomatedBankDebit'));

        // 2. Evaluate MoneyFlowUatService and PhaseGateReview:
        // When automated outbound disbursement / sandbox capabilities are evaluated:
        $uatService = app(MoneyFlowUatService::class);

        // When sandbox is unverified for direct automated capabilities, review is strictly no-go
        $unverifiedReview = $uatService->record(
            organizationIds: [$org->id],
            sandboxVerified: false,
            rollbackVerified: false,
            evidence: [
                'automated_outbound_disbursement' => [
                    'status' => 'blocked',
                    'mode' => 'manual_only',
                    'reason' => 'Direct owner provider (SePay) lacks automated outbound bank debit API; all owner refunds are executed manually.',
                    'automated_disbursement_ready' => false,
                ],
            ],
            reviewer: 'qa-sandbox-evaluator'
        );

        $this->assertSame('no-go', $unverifiedReview->decision);
        $this->assertSame('blocked', $unverifiedReview->evidence['sandbox_capabilities']['payout_submission']);
        $this->assertSame('blocked', $unverifiedReview->evidence['sandbox_capabilities']['refund']);
        $this->assertSame('blocked', $unverifiedReview->evidence['automated_outbound_disbursement']['status']);
        $this->assertSame('manual_only', $unverifiedReview->evidence['automated_outbound_disbursement']['mode']);
        $this->assertFalse($unverifiedReview->evidence['automated_outbound_disbursement']['automated_disbursement_ready']);

        // Even with sandbox verified for inbound receipts, outbound automated bank disbursement
        // must remain documented as manual-only / blocked, NOT claimed as automated production ready.
        $sandboxReview = $uatService->record(
            organizationIds: [$org->id],
            sandboxVerified: true,
            rollbackVerified: true,
            evidence: [
                'automated_outbound_disbursement' => [
                    'status' => 'blocked',
                    'mode' => 'manual_only',
                    'provider' => 'sepay',
                    'reason' => 'No direct automated bank debit API supported; manual owner execution with external evidence required.',
                    'production_ready_automated_payout' => false,
                ],
                'owner_refund_procedure' => 'manual_wire_with_encrypted_proof',
            ],
            reviewer: 'qa-sandbox-evaluator'
        );

        $this->assertDatabaseHas('phase_gate_reviews', [
            'id' => $sandboxReview->id,
            'phase' => 'phase-2-entry',
        ]);

        $persistedEvidence = $sandboxReview->fresh()->evidence;
        $this->assertSame('blocked', $persistedEvidence['automated_outbound_disbursement']['status']);
        $this->assertSame('manual_only', $persistedEvidence['automated_outbound_disbursement']['mode']);
        $this->assertFalse($persistedEvidence['automated_outbound_disbursement']['production_ready_automated_payout']);
        $this->assertSame('manual_wire_with_encrypted_proof', $persistedEvidence['owner_refund_procedure']);

        // Confirm system does not claim automated bank disbursement readiness
        $this->assertArrayHasKey('automated_outbound_disbursement', $persistedEvidence);
        $this->assertNotSame('automated', $persistedEvidence['automated_outbound_disbursement']['mode']);
    }

    // --------------------------------------------------------------------------
    // Test Helpers
    // --------------------------------------------------------------------------

    private function createVerifiedReceivingConnection(Venue $venue, string $accountNumber): array
    {
        $url = "/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/receiving-connection";
        $created = $this->postJson($url, [
            'bank_code' => 'MBBank',
            'account_name' => 'Aura Court Sandbox Owner',
            'account_number' => $accountNumber,
            'legal_owner_confirmed' => true,
        ])->assertCreated();

        $connection = ReceivingConnection::findOrFail((int) $created->json('data.id'));
        $secret = 'whsec_'.Str::random(48);

        $this->postJson($url."/{$connection->id}/credential", [
            'webhook_api_key' => $secret,
        ])->assertOk();

        // Complete sandbox verification event
        $this->sendSePayWebhook($connection, $secret, [
            'id' => 'verify-'.Str::uuid(),
            'transactionDate' => now()->toIso8601String(),
            'accountNumber' => $accountNumber,
            'transferType' => 'in',
            'transferAmount' => 1000,
            'referenceCode' => 'SANDBOX-VERIFY-INIT',
        ])->assertOk();

        $this->postJson($url."/{$connection->id}/select")->assertOk();

        return [$connection->fresh(), $secret];
    }

    private function sendSePayWebhook(ReceivingConnection $connection, string $secret, array $payload)
    {
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $response = $this->call('POST', '/api/v1/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Apikey '.$secret,
        ], $rawBody);

        if ($response->isSuccessful()) {
            $receipt = OwnerPaymentReceipt::where('provider_event_id', (string) $payload['id'])->first();
            if ($receipt && ! $receipt->processed_at) {
                app(OwnerPaymentAttemptService::class)->processReceipt($receipt->id);
            }
        }

        return $response;
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

            return $key.'='.$value;
        })->implode('&');

        return hash_hmac('sha256', $canonical, $checksumKey);
    }

    private function createDirectReservation(int $userId, int $organizationId, int $courtId, string $code, int $amount = 100000): Reservation
    {
        $reservation = Reservation::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'court_id' => $courtId,
            'code' => $code,
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => $amount,
            'hold_expires_at' => now()->addMinutes(20),
            'payment_model' => 'direct_owner_v1',
        ]);

        $slot = ReservationSlot::create([
            'organization_id' => $organizationId,
            'reservation_id' => $reservation->id,
            'court_id' => $courtId,
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(11, 0),
            'unit_price' => $amount,
            'status' => 'held',
        ]);

        ReservationEntitlement::create([
            'organization_id' => $organizationId,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'user_id' => $userId,
            'court_id' => $courtId,
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->ends_at,
            'amount' => $amount,
            'status' => 'held',
        ]);

        return $reservation;
    }

    private function createPaidDirectReservationFixture(): array
    {
        $organization = Organization::create([
            'name' => 'Refund Sandbox Club',
            'slug' => 'refund-sandbox-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'partner_status' => 'approved',
        ]);

        $owner = User::factory()->create(['email_verified_at' => now()]);
        $payer = User::factory()->create(['email_verified_at' => now()]);

        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => OrganizationMember::ROLE_ORGANIZATION_OWNER,
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_id' => $organization->id,
            'user_id' => $payer->id,
            'role' => OrganizationMember::ROLE_CUSTOMER,
            'status' => 'active',
        ]);

        $venue = Venue::create([
            'organization_id' => $organization->id,
            'name' => 'Refund Sandbox Venue',
            'slug' => 'refund-sandbox-venue-'.Str::lower(Str::random(8)),
            'status' => 'published',
            'is_active' => true,
        ]);
        $court = Court::create([
            'venue_id' => $venue->id,
            'name' => 'Sandbox Court 1',
            'is_active' => true,
        ]);

        $coupon = Coupon::create([
            'organization_id' => $organization->id,
            'code' => 'SANDBOX10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'usage_limit' => 10,
            'usage_count' => 1,
            'is_active' => true,
        ]);

        $reservation = Reservation::create([
            'organization_id' => $organization->id,
            'user_id' => $payer->id,
            'court_id' => $court->id,
            'code' => 'RSV-RF-SB-'.Str::upper(Str::random(8)),
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 100000,
            'hold_expires_at' => now()->addMinutes(30),
            'payment_model' => 'direct_owner_v1',
            'metadata' => [
                'coupon_id' => $coupon->id,
                'booking_policy_snapshot' => [
                    'version' => 1,
                    'settings' => [
                        'cancellation' => [
                            'deadline_hours' => 24,
                            'fee_type' => 'percentage',
                            'fee_value' => 10,
                            'refund_due_hours' => 48,
                        ],
                    ],
                ],
            ],
        ]);

        $slot = ReservationSlot::create([
            'organization_id' => $organization->id,
            'reservation_id' => $reservation->id,
            'court_id' => $court->id,
            'starts_at' => now()->addDays(3)->setTime(14, 0),
            'ends_at' => now()->addDays(3)->setTime(15, 0),
            'unit_price' => 100000,
            'status' => 'held',
        ]);

        ReservationEntitlement::create([
            'organization_id' => $organization->id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'user_id' => $payer->id,
            'court_id' => $court->id,
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->ends_at,
            'amount' => 100000,
            'status' => 'held',
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
        $connection->setAccountDetails('Sandbox Refund Owner', '0555666777');
        $secret = 'whsec_'.Str::random(48);
        $connection->webhook_secret_ciphertext = Crypt::encryptString($secret);
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();

        $attempt = app(OwnerPaymentAttemptService::class)->createAttempt(
            $connection,
            $payer,
            'booking',
            $reservation->code,
            'ACRFSB'.Str::upper(Str::random(10)),
            100000,
            'VND',
            now()->addMinutes(20),
            'refund-sandbox-attempt-'.Str::uuid()
        );

        $payload = [
            'id' => 'refund-sandbox-init-'.Str::uuid(),
            'transactionDate' => now()->addSeconds(2)->toIso8601String(),
            'accountNumber' => '0555666777',
            'code' => $attempt->reference,
            'content' => $attempt->reference,
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'BANK-RF-SB-TEST',
            'currency' => 'VND',
        ];

        $this->sendSePayWebhook($connection, $secret, $payload)->assertOk();

        $reservation->refresh();
        $reservation->slots()->where('status', 'held')->update(['status' => 'confirmed']);
        $reservation->entitlements()->where('status', 'held')->update(['status' => 'active']);

        return [
            'organization' => $organization,
            'owner' => $owner,
            'payer' => $payer,
            'venue' => $venue,
            'reservation' => $reservation->fresh(),
            'slots' => collect([$slot]),
            'coupon' => $coupon,
            'attempt' => $attempt->fresh(),
        ];
    }
}
