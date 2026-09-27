<?php

namespace Tests\Feature\Payment;

use App\Models\Journal;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\PaymentIntent;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\ReservationEntitlement;
use App\Models\ReservationSlot;
use App\Models\Venue;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerReservationPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiver_change_keeps_existing_payment_instructions_and_confirms_only_from_verified_receipt(): void
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

        [$firstConnection, $firstSecret] = $this->createVerifiedConnection($venue, '0123456789');
        $reservation = $this->createDirectReservation($owner->id, $organization->id, $court->id, 'RSV-OLD-RECEIVER');
        $url = "/api/v2/reservations/{$reservation->code}/payment";
        $firstResponse = $this->postJson($url)->assertCreated();
        $firstAttempt = $firstResponse->json('payment_attempt');
        $firstResponse->assertJsonPath('payment_attempt.status', OwnerPaymentAttempt::STATUS_PENDING)
            ->assertJsonPath('payment_attempt.receiver.account_number', '0123456789')
            ->assertJsonPath('payment_attempt.amount', 100000);
        $this->assertStringContainsString('MB-0123456789', $firstAttempt['qr_url']);

        [$secondConnection] = $this->createVerifiedConnection($venue, '9988776655');
        $sameAttempt = $this->postJson($url)->assertOk()->json('payment_attempt');
        $this->assertSame($firstAttempt['id'], $sameAttempt['id']);
        $this->assertSame($firstAttempt['reference'], $sameAttempt['reference']);
        $this->assertSame($firstAttempt['qr_url'], $sameAttempt['qr_url']);
        $this->assertSame('0123456789', $sameAttempt['receiver']['account_number']);
        $this->assertNotSame($firstConnection->id, $secondConnection->id);

        $newReservation = $this->createDirectReservation($owner->id, $organization->id, $court->id, 'RSV-NEW-RECEIVER');
        $newPayment = $this->postJson("/api/v2/reservations/{$newReservation->code}/payment")
            ->assertCreated()
            ->assertJsonPath('payment_attempt.receiver.account_number', '9988776655')
            ->json('payment_attempt');

        $this->sendEvent($firstConnection, $firstSecret, [
            'id' => 'receiver-bound-payment-1',
            'transactionDate' => now()->addSeconds(3)->toIso8601String(),
            'accountNumber' => '0123456789',
            'code' => $firstAttempt['reference'],
            'content' => $firstAttempt['transfer_content'],
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'BANK-RECEIPT-1',
        ])->assertOk();

        $reservation->refresh();
        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame('direct_owner_v1', $reservation->payment_model);
        $this->assertDatabaseHas('reservation_slots', ['reservation_id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('reservation_entitlements', ['reservation_id' => $reservation->id, 'status' => 'active']);
        $this->assertDatabaseHas('owner_payment_attempts', ['id' => $firstAttempt['id'], 'status' => 'paid']);
        $this->assertDatabaseHas('owner_payment_attempts', ['id' => $newPayment['id'], 'receiving_connection_id' => $secondConnection->id]);
        $this->assertDatabaseCount('owner_payment_allocations', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseMissing('journals', ['ledger_entity' => 'platform', 'payment_model' => 'direct_owner_v1']);
        $this->assertDatabaseMissing('journals', ['ledger_entity' => 'owner', 'event_type' => 'owner.tenant_payable']);

        $journal = Journal::query()->where('event_type', 'owner.direct_receipt')->firstOrFail();
        $this->assertSame('owner', $journal->ledger_entity);
        $this->assertSame('direct_owner_v1', $journal->payment_model);
        $this->assertSame(100000, (int) $journal->total_debit);
    }

    public function test_payment_status_is_owner_scoped_and_disabled_outside_the_cohort(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $organization = $fixture['org1']['org'];
        $reservation = $this->createDirectReservation($owner->id, $organization->id, $fixture['org1']['court1']->id, 'RSV-PRIVATE-PAY');
        config([
            'features.flags.direct_owner_payments' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);

        Sanctum::actingAs($fixture['org2']['owner']);
        $this->getJson("/api/v2/reservations/{$reservation->code}/payment")->assertNotFound();

        Sanctum::actingAs($owner);
        config(['features.flags.direct_owner_payments' => false]);
        $this->postJson("/api/v2/reservations/{$reservation->code}/payment")->assertNotFound();
        $this->assertDatabaseCount('owner_payment_attempts', 0);
    }

    public function test_direct_payment_cohort_cannot_create_pooled_topups_but_keeps_legacy_deposit_history(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $organization = $fixture['org1']['org'];
        Sanctum::actingAs($owner);
        config([
            'features.flags.direct_owner_payments' => true,
            'features.pilot_organizations' => [(string) $organization->id],
        ]);
        PaymentIntent::create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'reference' => 'LEGACY-DEP-1',
            'provider' => 'sepay',
            'idempotency_key' => 'legacy-deposit-1',
            'amount' => 50000,
            'currency' => 'VND',
            'status' => 'pending',
            'metadata' => ['type' => 'wallet_deposit'],
            'expires_at' => now()->addDay(),
        ]);

        $this->postJson('/api/v1/wallet/deposits', [
            'organization_id' => $organization->id,
            'amount' => 100000,
            'idempotency_key' => 'new-pooled-deposit',
        ])->assertConflict()->assertJsonPath('code', 'DIRECT_OWNER_PAYMENTS_ENABLED');

        $this->getJson("/api/v1/wallet/deposits?organization_id={$organization->id}")
            ->assertOk()
            ->assertJsonCount(1, 'deposits')
            ->assertJsonPath('deposits.0.reference', 'LEGACY-DEP-1');
        $this->assertDatabaseHas('payment_intents', ['reference' => 'LEGACY-DEP-1', 'status' => 'pending']);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function createDirectReservation(int $userId, int $organizationId, int $courtId, string $code): Reservation
    {
        $slotHour = str_contains($code, 'NEW') ? 11 : 9;
        $reservation = Reservation::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'court_id' => $courtId,
            'code' => $code,
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 100000,
            'hold_expires_at' => now()->addMinutes(20),
            'payment_model' => 'direct_owner_v1',
        ]);
        $slot = ReservationSlot::create([
            'organization_id' => $organizationId,
            'reservation_id' => $reservation->id,
            'court_id' => $courtId,
            'starts_at' => now()->addDays(2)->setTime($slotHour, 0),
            'ends_at' => now()->addDays(2)->setTime($slotHour + 1, 0),
            'unit_price' => 100000,
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
            'amount' => 100000,
            'status' => 'held',
        ]);

        return $reservation;
    }

    private function createVerifiedConnection(Venue $venue, string $accountNumber): array
    {
        $url = "/api/v1/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/receiving-connection";
        $created = $this->postJson($url, [
            'bank_code' => 'MBBank',
            'account_name' => 'Aura Court Owner',
            'account_number' => $accountNumber,
            'legal_owner_confirmed' => true,
        ])->assertCreated();
        $connection = ReceivingConnection::findOrFail((int) $created->json('data.id'));
        $secret = 'whsec_'.Str::random(48);
        $this->postJson($url."/{$connection->id}/credential", ['hmac_secret' => $secret])->assertOk();
        $this->sendEvent($connection, $secret, [
            'id' => 'verify-'.Str::uuid(),
            'transactionDate' => now()->toIso8601String(),
            'accountNumber' => $accountNumber,
            'transferType' => 'in',
            'transferAmount' => 1000,
            'referenceCode' => 'VERIFICATION',
        ])->assertOk();
        $this->postJson($url."/{$connection->id}/select")->assertOk();

        return [$connection->fresh(), $secret];
    }

    private function sendEvent(ReceivingConnection $connection, string $secret, array $payload)
    {
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
        $response = $this->call('POST', '/api/v1/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
            'HTTP_X_SEPAY_SIGNATURE' => $signature,
        ], $rawBody);

        if ($response->isSuccessful()) {
            $receipt = OwnerPaymentReceipt::where('provider_event_id', (string) $payload['id'])->first();
            if ($receipt && ! $receipt->processed_at) {
                app(OwnerPaymentAttemptService::class)->processReceipt($receipt->id);
            }
        }

        return $response;
    }
}
