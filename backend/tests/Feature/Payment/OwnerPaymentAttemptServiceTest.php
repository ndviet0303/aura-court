<?php

namespace Tests\Feature\Payment;

use App\Domain\Booking\Actions\ExpirePendingBookings;
use App\Models\Journal;
use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\Venue;
use App\Models\WalletAccount;
use App\Services\Infrastructure\ScheduledRecoveryService;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\SePayReceivingConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerPaymentAttemptServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attempt_snapshots_receiver_and_purpose_and_duplicate_event_allocates_once(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $venue = $fixture['org1']['venue1'];
        Sanctum::actingAs($owner);
        [$connection, $secret] = $this->createVerifiedConnection($venue);
        $legacyWallet = WalletAccount::withoutGlobalScopes()->updateOrCreate([
            'organization_id' => $venue->organization_id,
            'user_id' => $owner->id,
            'currency' => 'VND',
        ], [
            'balance' => 500000,
            'status' => 'active',
        ]);

        $expiresAt = now()->addMinutes(15);
        $reservation = Reservation::create([
            'organization_id' => $venue->organization_id,
            'user_id' => $owner->id,
            'court_id' => $fixture['org1']['court1']->id,
            'code' => 'booking-341',
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 100000,
            'hold_expires_at' => $expiresAt,
            'payment_model' => 'direct_owner_v1',
        ]);
        $slot = ReservationSlot::create([
            'organization_id' => $venue->organization_id,
            'reservation_id' => $reservation->id,
            'court_id' => $fixture['org1']['court1']->id,
            'starts_at' => now()->addDays(2)->setTime(9, 0),
            'ends_at' => now()->addDays(2)->setTime(10, 0),
            'unit_price' => 100000,
            'status' => 'held',
        ]);
        ReservationEntitlement::create([
            'organization_id' => $venue->organization_id,
            'reservation_id' => $reservation->id,
            'reservation_slot_id' => $slot->id,
            'user_id' => $owner->id,
            'court_id' => $fixture['org1']['court1']->id,
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->ends_at,
            'amount' => 100000,
            'status' => 'held',
        ]);
        $service = app(OwnerPaymentAttemptService::class);
        $attempt = $service->createAttempt(
            $connection,
            $owner,
            'booking',
            'booking-341',
            'AC-BOOK-341',
            100000,
            'VND',
            $expiresAt,
            'checkout-341'
        );
        $retried = $service->createAttempt(
            $connection,
            $owner,
            'booking',
            'booking-341',
            'AC-BOOK-341',
            100000,
            'VND',
            $expiresAt,
            'checkout-341'
        );

        $this->assertSame($attempt->id, $retried->id);
        $this->assertDatabaseCount('owner_payment_attempts', 1);
        $this->assertSame('0123456789', $attempt->receiverSnapshot()['account_number']);
        $this->assertSame('booking', $attempt->purposeSnapshot()['purpose']);
        $this->assertSame('AC-BOOK-341', $attempt->purposeSnapshot()['reference']);
        $this->assertStringNotContainsString('0123456789', $attempt->receiver_snapshot_ciphertext);

        $payload = [
            'id' => 'payment-event-341',
            'transactionDate' => now()->addSeconds(5)->toIso8601String(),
            'accountNumber' => '0123456789',
            'code' => 'AC-BOOK-341',
            'content' => 'AC-BOOK-341 booking payment',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'BANK-REF-341',
        ];
        $this->sendEvent($connection, $secret, $payload)->assertOk()->assertExactJson(['success' => true]);
        $this->sendEvent($connection, $secret, $payload)->assertOk()->assertExactJson(['success' => true]);

        $attempt->refresh();
        $receipt = OwnerPaymentReceipt::where('provider_event_id', 'payment-event-341')->firstOrFail();
        $allocation = OwnerPaymentAllocation::where('payment_receipt_id', $receipt->id)->firstOrFail();
        $this->assertSame(OwnerPaymentAttempt::STATUS_PAID, $attempt->status);
        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame('allocated', $receipt->status);
        $this->assertSame('BANK-REF-341', $receipt->reference_code);
        $this->assertSame('AC-BOOK-341', $receipt->payment_code);
        $this->assertSame(100000, $allocation->amount);
        $this->assertSame('booking', $allocation->purpose);
        $this->assertSame('0123456789', $allocation->receiverSnapshot()['account_number']);
        $this->assertSame('booking-341', $allocation->purposeSnapshot()['purpose_id']);
        $this->assertDatabaseCount('owner_payment_receipts', 2); // Includes the connection verification event.
        $this->assertDatabaseCount('owner_payment_allocations', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(500000, (int) $legacyWallet->fresh()->balance);

        $journal = Journal::query()
            ->where('event_type', 'owner.direct_receipt')
            ->where('event_id', 'owner-receipt:'.$receipt->id)
            ->firstOrFail()
            ->load('entries.account');
        $this->assertSame('owner', $journal->ledger_entity);
        $this->assertSame('direct_owner_v1', $journal->payment_model);
        $this->assertSame(100000, $journal->total_debit);
        $this->assertSame($journal->total_debit, $journal->total_credit);
        $this->assertSame(0, $journal->metadata['commission']);
        $this->assertSame(['owner_bank_cash', 'owner_direct_revenue'], $journal->entries->pluck('account.code')->all());
        $this->assertDatabaseCount('journals', 1);
        $this->assertSame(500000, (int) $legacyWallet->fresh()->balance);

        $journal = Journal::query()
            ->where('event_type', 'owner.direct_receipt')
            ->where('event_id', 'owner-receipt:'.$receipt->id)
            ->firstOrFail()
            ->load('entries.account');
        $this->assertSame('owner', $journal->ledger_entity);
        $this->assertSame('direct_owner_v1', $journal->payment_model);
        $this->assertSame(100000, $journal->total_debit);
        $this->assertSame($journal->total_debit, $journal->total_credit);
        $this->assertSame(0, $journal->metadata['commission']);
        $this->assertSame(['owner_bank_cash', 'owner_direct_revenue'], $journal->entries->pluck('account.code')->all());
    }

    public function test_foreign_receiver_partial_excess_late_unmatched_and_wrong_currency_are_exceptions(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $venue = $fixture['org1']['venue1'];
        Sanctum::actingAs($owner);
        [$connection, $secret] = $this->createVerifiedConnection($venue);

        $attempt = app(OwnerPaymentAttemptService::class)->createAttempt(
            $connection,
            $owner,
            'booking',
            'booking-900',
            'AC-BOOK-900',
            100000,
            'VND',
            now()->addMinutes(10),
            'checkout-900'
        );

        $events = [
            ['id' => 'foreign-900', 'accountNumber' => '9999999999', 'transferAmount' => 100000, 'transactionDate' => now()->addSeconds(5)->toIso8601String(), 'currency' => 'VND', 'expected' => 'foreign_receiver', 'status' => 'exception'],
            ['id' => 'partial-900', 'accountNumber' => '0123456789', 'transferAmount' => 99999, 'transactionDate' => now()->addSeconds(5)->toIso8601String(), 'currency' => 'VND', 'expected' => 'partial_amount', 'status' => 'exception'],
            ['id' => 'excess-900', 'accountNumber' => '0123456789', 'transferAmount' => 100001, 'transactionDate' => now()->addSeconds(5)->toIso8601String(), 'currency' => 'VND', 'expected' => 'excess_amount', 'status' => 'exception'],
            ['id' => 'late-900', 'accountNumber' => '0123456789', 'transferAmount' => 100000, 'transactionDate' => now()->addMinutes(20)->toIso8601String(), 'currency' => 'VND', 'expected' => 'transfer_after_expiry', 'status' => 'late'],
            ['id' => 'early-900', 'accountNumber' => '0123456789', 'transferAmount' => 100000, 'transactionDate' => now()->subMinutes(5)->toIso8601String(), 'currency' => 'VND', 'expected' => 'transfer_before_attempt', 'status' => 'exception'],
            ['id' => 'currency-900', 'accountNumber' => '0123456789', 'transferAmount' => 100000, 'transactionDate' => now()->addSeconds(5)->toIso8601String(), 'currency' => 'USD', 'expected' => 'currency_mismatch', 'status' => 'exception'],
            ['id' => 'unmatched-900', 'accountNumber' => '0123456789', 'transferAmount' => 100000, 'transactionDate' => now()->addSeconds(5)->toIso8601String(), 'currency' => 'VND', 'code' => null, 'expected' => 'missing_payment_code', 'status' => 'unmatched'],
        ];

        foreach ($events as $event) {
            $payload = [
                'id' => $event['id'],
                'transactionDate' => $event['transactionDate'],
                'accountNumber' => $event['accountNumber'],
                'code' => array_key_exists('code', $event) ? $event['code'] : 'AC-BOOK-900',
                'transferType' => 'in',
                'transferAmount' => $event['transferAmount'],
                'referenceCode' => 'BANK-'.$event['id'],
                'currency' => $event['currency'],
            ];

            $this->sendEvent($connection, $secret, $payload)->assertOk();
            $receipt = OwnerPaymentReceipt::where('provider_event_id', $event['id'])->firstOrFail();
            $this->assertSame($event['expected'], $receipt->exception_code);
            $this->assertSame($event['status'], $receipt->status);
        }

        $wrongKey = json_encode([
            'id' => 'wrong-key-900',
            'transactionDate' => now()->addSeconds(5)->toIso8601String(),
            'accountNumber' => '0123456789',
            'code' => 'AC-BOOK-900',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'BANK-WRONG-KEY',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->call('POST', $this->webhookUrl($connection), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Apikey '.$secret.'-wrong',
        ], $wrongKey)->assertUnauthorized();

        $attempt->refresh();
        $this->assertSame(OwnerPaymentAttempt::STATUS_PENDING, $attempt->status);
        $this->assertDatabaseMissing('owner_payment_receipts', ['provider_event_id' => 'wrong-key-900']);
        $this->assertDatabaseCount('owner_payment_allocations', 0);
    }

    public function test_lost_response_reuses_the_durable_receipt_and_recovery_processes_it_once(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $venue = $fixture['org1']['venue1'];
        Sanctum::actingAs($owner);
        [$connection, $secret] = $this->createVerifiedConnection($venue);
        [$verification, $rawBody, $authorization] = $this->verifiedEvent($connection, $secret, [
            'id' => 'lost-response-event',
            'transactionDate' => now()->subMinute()->toIso8601String(),
            'accountNumber' => '0123456789',
            'code' => 'AC-ORDER-100',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'BANK-LOST-1',
        ]);
        $verificationService = app(SePayReceivingConnectionService::class);
        $payments = app(OwnerPaymentAttemptService::class);
        $verified = $verificationService->verifyInboundWebhook($connection, $authorization, $rawBody);
        $receipt = $payments->receiveVerifiedEvent($connection, $verified);
        $replayVerified = $verificationService->verifyInboundWebhook($connection, $authorization, $rawBody);
        $replayedReceipt = $payments->receiveVerifiedEvent($connection, $replayVerified);

        $this->assertSame($receipt->id, $replayedReceipt->id);
        $this->assertNull($receipt->processed_at);
        $this->assertDatabaseCount('owner_payment_receipts', 2); // Verification event plus one lost-response event.

        $receipt->forceFill(['next_attempt_at' => now()->subSecond()])->save();
        $this->assertSame(1, app(ScheduledRecoveryService::class)->recoverOwnerPaymentReceipts());
        $payments->processReceipt((int) $receipt->id);

        $receipt->refresh();
        $this->assertSame(OwnerPaymentReceipt::STATUS_UNMATCHED, $receipt->status);
        $this->assertNotNull($receipt->processed_at);
        $this->assertDatabaseHas('finance_exceptions', [
            'type' => 'owner_payment_unmatched_reference',
            'reference' => 'owner-receipt:'.$receipt->id,
            'status' => 'open',
        ]);

        $finalReplay = $payments->receiveVerifiedEvent($connection, $replayVerified);
        $this->assertSame($receipt->id, $finalReplay->id);
        $this->assertDatabaseCount('owner_payment_receipts', 2);
        $this->assertDatabaseCount('owner_payment_allocations', 0);

        $attemptCreatedAfterReceipt = $payments->createAttempt(
            $connection,
            $owner,
            'booking',
            'booking-100',
            'AC-ORDER-100',
            100000,
            'VND',
            now()->addMinutes(10),
            'checkout-100'
        );
        $this->assertSame(OwnerPaymentAttempt::STATUS_PENDING, $attemptCreatedAfterReceipt->status);
        $this->assertNull($receipt->fresh()->payment_attempt_id);
    }

    public function test_receipt_processing_exhaustion_is_bounded_and_creates_an_exception(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $venue = $fixture['org1']['venue1'];
        Sanctum::actingAs($owner);
        [$connection, $secret] = $this->createVerifiedConnection($venue);
        [$unused, $rawBody, $authorization] = $this->verifiedEvent($connection, $secret, [
            'id' => 'exhausted-event',
            'transactionDate' => now()->addSeconds(5)->toIso8601String(),
            'accountNumber' => '0123456789',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'BANK-EXHAUSTED',
        ]);
        $verified = app(SePayReceivingConnectionService::class)->verifyInboundWebhook($connection, $authorization, $rawBody);
        $receipt = app(OwnerPaymentAttemptService::class)->receiveVerifiedEvent($connection, $verified);
        $payments = app(OwnerPaymentAttemptService::class);

        for ($i = 0; $i < 8; $i++) {
            $payments->markReceiptProcessingFailure((int) $receipt->id, new \RuntimeException('provider processing failure'));
        }

        $receipt->refresh();
        $this->assertSame(OwnerPaymentReceipt::STATUS_EXCEPTION, $receipt->status);
        $this->assertSame(8, $receipt->processing_attempts);
        $this->assertSame(\RuntimeException::class, $receipt->processing_error);
        $this->assertNotNull($receipt->processed_at);
        $this->assertDatabaseHas('finance_exceptions', [
            'type' => 'owner_payment_processing_exhausted',
            'reference' => 'owner-receipt:'.$receipt->id,
            'status' => 'open',
        ]);
    }

    public function test_expiry_sweep_and_matching_receipt_race_yields_exactly_one_outcome(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $venue = $fixture['org1']['venue1'];
        $court = $fixture['org1']['court1'];
        Sanctum::actingAs($owner);
        [$connection, $secret] = $this->createVerifiedConnection($venue);

        $payments = app(OwnerPaymentAttemptService::class);
        $sweep = app(ExpirePendingBookings::class);

        // --- Ordering A: the expiry sweep commits first; a valid-timing receipt loses the race. ---
        $reservationA = Reservation::create([
            'organization_id' => $venue->organization_id,
            'user_id' => $owner->id,
            'court_id' => $court->id,
            'code' => 'RES-RACE-A',
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 100000,
            'hold_expires_at' => now()->subSeconds(30), // Hold lapsed -> a sweep candidate.
            'payment_model' => 'direct_owner_v1',
        ]);
        $slotA = ReservationSlot::create([
            'organization_id' => $venue->organization_id,
            'reservation_id' => $reservationA->id,
            'court_id' => $court->id,
            'starts_at' => now()->addDays(2)->setTime(9, 0),
            'ends_at' => now()->addDays(2)->setTime(10, 0),
            'unit_price' => 100000,
            'status' => 'held',
        ]);
        $entitlementA = ReservationEntitlement::create([
            'organization_id' => $venue->organization_id,
            'reservation_id' => $reservationA->id,
            'reservation_slot_id' => $slotA->id,
            'user_id' => $owner->id,
            'court_id' => $court->id,
            'starts_at' => $slotA->starts_at,
            'ends_at' => $slotA->ends_at,
            'amount' => 100000,
            'status' => 'held',
        ]);
        // Attempt window is still open (future expiry) so the receipt is valid-timing, not "late".
        $attemptA = $payments->createAttempt($connection, $owner, 'booking', 'RES-RACE-A', 'AC-RACE-A', 100000, 'VND', now()->addMinutes(10), 'checkout-race-a');

        $sweep->execute();

        $this->assertSame('expired', $reservationA->fresh()->status);
        $this->assertSame('expired', $slotA->fresh()->status);
        $this->assertSame('expired', $entitlementA->fresh()->status);
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, $attemptA->fresh()->status);

        $this->sendEvent($connection, $secret, [
            'id' => 'receipt-race-a',
            'transactionDate' => now()->toIso8601String(),
            'accountNumber' => '0123456789',
            'code' => 'AC-RACE-A',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'BANK-RACE-A',
        ])->assertOk();

        $receiptA = OwnerPaymentReceipt::where('provider_event_id', 'receipt-race-a')->firstOrFail();
        $this->assertSame('attempt_not_pending', $receiptA->exception_code);
        $this->assertSame('exception', $receiptA->status);
        $this->assertSame('expired', $reservationA->fresh()->status); // Never flips back to confirmed.
        $this->assertSame(OwnerPaymentAttempt::STATUS_EXPIRED, $attemptA->fresh()->status);
        $this->assertDatabaseMissing('owner_payment_allocations', ['payment_attempt_id' => $attemptA->id]);

        // --- Ordering B: the receipt commits first; the later sweep must not touch the confirmed hold. ---
        $reservationB = Reservation::create([
            'organization_id' => $venue->organization_id,
            'user_id' => $owner->id,
            'court_id' => $court->id,
            'code' => 'RES-RACE-B',
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 100000,
            'hold_expires_at' => now()->subSeconds(30),
            'payment_model' => 'direct_owner_v1',
        ]);
        $slotB = ReservationSlot::create([
            'organization_id' => $venue->organization_id,
            'reservation_id' => $reservationB->id,
            'court_id' => $court->id,
            'starts_at' => now()->addDays(3)->setTime(9, 0),
            'ends_at' => now()->addDays(3)->setTime(10, 0),
            'unit_price' => 100000,
            'status' => 'held',
        ]);
        ReservationEntitlement::create([
            'organization_id' => $venue->organization_id,
            'reservation_id' => $reservationB->id,
            'reservation_slot_id' => $slotB->id,
            'user_id' => $owner->id,
            'court_id' => $court->id,
            'starts_at' => $slotB->starts_at,
            'ends_at' => $slotB->ends_at,
            'amount' => 100000,
            'status' => 'held',
        ]);
        $attemptB = $payments->createAttempt($connection, $owner, 'booking', 'RES-RACE-B', 'AC-RACE-B', 100000, 'VND', now()->addMinutes(10), 'checkout-race-b');

        $this->sendEvent($connection, $secret, [
            'id' => 'receipt-race-b',
            'transactionDate' => now()->toIso8601String(),
            'accountNumber' => '0123456789',
            'code' => 'AC-RACE-B',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'BANK-RACE-B',
        ])->assertOk();

        $this->assertSame('confirmed', $reservationB->fresh()->status);
        $this->assertSame(OwnerPaymentAttempt::STATUS_PAID, $attemptB->fresh()->status);
        $this->assertSame(1, OwnerPaymentAllocation::where('payment_attempt_id', $attemptB->id)->count());

        $sweep->execute();

        $this->assertSame('confirmed', $reservationB->fresh()->status);
        $this->assertSame('confirmed', $slotB->fresh()->status);
        $this->assertSame(OwnerPaymentAttempt::STATUS_PAID, $attemptB->fresh()->status);
        $this->assertSame(1, OwnerPaymentAllocation::where('payment_attempt_id', $attemptB->id)->count());
    }

    private function createVerifiedConnection(Venue $venue): array
    {
        $url = "/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/receiving-connection";
        $created = $this->postJson($url, [
            'bank_code' => 'MBBank',
            'account_name' => 'Aura Court Owner',
            'account_number' => '0123456789',
            'legal_owner_confirmed' => true,
        ])->assertCreated();

        $connection = ReceivingConnection::findOrFail((int) $created->json('data.id'));
        $secret = 'whsec_'.Str::random(48);
        $this->postJson($url."/{$connection->id}/credential", ['webhook_api_key' => $secret])->assertOk();

        $verificationEvent = [
            'id' => 'verify-'.Str::uuid(),
            'transactionDate' => now()->addSeconds(5)->toIso8601String(),
            'accountNumber' => '0123456789',
            'transferType' => 'in',
            'transferAmount' => 1000,
            'referenceCode' => 'VERIFICATION',
        ];
        $this->sendEvent($connection, $secret, $verificationEvent)->assertOk();
        $this->postJson($url."/{$connection->id}/select")->assertOk()->assertJsonPath('data.is_selected', true);

        return [$connection->fresh(), $secret];
    }

    private function sendEvent(ReceivingConnection $connection, string $secret, array $payload)
    {
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $response = $this->call('POST', $this->webhookUrl($connection), [], [], [], [
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

    private function verifiedEvent(ReceivingConnection $connection, string $secret, array $payload): array
    {
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $authorization = 'Apikey '.$secret;
        $verified = app(SePayReceivingConnectionService::class)->verifyInboundWebhook($connection, $authorization, $rawBody);

        return [$verified, $rawBody, $authorization];
    }

    private function webhookUrl(ReceivingConnection $connection): string
    {
        return '/api/v1/webhooks/sepay/connections/'.$connection->routing_id;
    }
}
