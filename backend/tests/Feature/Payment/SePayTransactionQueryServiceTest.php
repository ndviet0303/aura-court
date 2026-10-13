<?php

namespace Tests\Feature\Payment;

use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use App\Models\Reservation;
use App\Models\Venue;
use App\Services\Payment\OwnerPaymentAttemptService;
use App\Services\Payment\SePayTransactionQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SePayTransactionQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_query_uses_encrypted_bearer_token_and_ingests_replays_once(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $venue = $fixture['org1']['venue1'];
        Sanctum::actingAs($owner);
        [$connection] = $this->createVerifiedConnection($venue);

        $apiToken = Str::random(64);
        $providerAccountId = 'bank-account-7788';
        $this->postJson($this->connectionUrl($venue)."/{$connection->id}/query-credential", [
            'api_token' => $apiToken,
            'provider_account_id' => $providerAccountId,
        ])->assertOk()
            ->assertJsonPath('data.query_api_configured', true)
            ->assertJsonPath('data.query_api_version', 1)
            ->assertDontSee($apiToken);

        $connection->refresh();
        $this->assertNotSame($apiToken, $connection->api_token_ciphertext);
        $this->assertSame($apiToken, Crypt::decryptString($connection->api_token_ciphertext));
        $this->assertArrayNotHasKey('api_token_ciphertext', $connection->toArray());

        $attempt = app(OwnerPaymentAttemptService::class)->createAttempt(
            $connection,
            $owner,
            'booking',
            'booking-query-1',
            'AC-QUERY-1',
            100000,
            'VND',
            now()->addMinutes(15),
            'query-checkout-1'
        );

        Reservation::create([
            'organization_id' => $venue->organization_id,
            'user_id' => $owner->id,
            'court_id' => $fixture['org1']['court1']->id,
            'code' => 'booking-query-1',
            'status' => 'held',
            'currency' => 'VND',
            'total_amount' => 100000,
            'hold_expires_at' => now()->addMinutes(15),
            'payment_model' => 'direct_owner_v1',
        ]);

        $transaction = [
            'id' => 881001,
            'gateway' => 'MBBank',
            'transactionDate' => now()->addSeconds(5)->toIso8601String(),
            'accountNumber' => '0123456789',
            'code' => 'AC-QUERY-1',
            'content' => 'AC-QUERY-1 payment',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'accumulated' => 100000,
            'subAccount' => null,
            'referenceCode' => 'BANK-QUERY-881001',
            'description' => 'Test transfer',
        ];
        Http::fake([
            'https://userapi.sepay.vn/v2/transactions*' => Http::response(['data' => [$transaction]], 200),
        ]);
        Http::preventStrayRequests();

        $query = app(SePayTransactionQueryService::class);
        $first = $query->sync($connection);
        $receipt = OwnerPaymentReceipt::where('provider_event_id', '881001')->firstOrFail();
        if (! $receipt->processed_at) {
            app(OwnerPaymentAttemptService::class)->processReceipt((int) $receipt->id);
        }

        $second = $query->sync($connection->fresh());

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://userapi.sepay.vn/v2/transactions')
            && $request->hasHeader('Authorization', 'Bearer '.$apiToken)
            && str_contains($request->url(), 'bank_account_id='.$providerAccountId));

        $this->assertSame(1, $first['transactions_received']);
        $this->assertSame('881001', $first['last_queried_transaction_id']);
        $this->assertSame(1, $second['transactions_received']);
        $this->assertSame('api_query', $receipt->fresh()->source);
        $this->assertSame('allocated', $receipt->fresh()->status);
        $this->assertSame(OwnerPaymentAttempt::STATUS_PAID, $attempt->fresh()->status);
        $this->assertDatabaseCount('owner_payment_receipts', 2); // Verification event plus one queried payment.
        $this->assertDatabaseCount('owner_payment_allocations', 1);
    }

    private function createVerifiedConnection(Venue $venue): array
    {
        $url = $this->connectionUrl($venue);
        $created = $this->postJson($url, [
            'bank_code' => 'MBBank',
            'account_name' => 'Aura Court Owner',
            'account_number' => '0123456789',
            'legal_owner_confirmed' => true,
        ])->assertCreated();

        $connection = ReceivingConnection::findOrFail((int) $created->json('data.id'));
        $hmacSecret = 'whsec_'.Str::random(48);
        $this->postJson($url."/{$connection->id}/credential", ['hmac_secret' => $hmacSecret])->assertOk();
        $payload = [
            'id' => 'query-verification-'.Str::uuid(),
            'transactionDate' => now()->addSeconds(5)->toIso8601String(),
            'accountNumber' => '0123456789',
            'transferType' => 'in',
            'transferAmount' => 1000,
            'referenceCode' => 'VERIFY-QUERY',
        ];
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $signature = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $hmacSecret);
        $this->call('POST', '/api/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
            'HTTP_X_SEPAY_SIGNATURE' => $signature,
        ], $rawBody)->assertOk();

        $receipt = OwnerPaymentReceipt::where('provider_event_id', (string) $payload['id'])->firstOrFail();
        if (! $receipt->processed_at) {
            app(OwnerPaymentAttemptService::class)->processReceipt((int) $receipt->id);
        }
        $this->postJson($url."/{$connection->id}/select")->assertOk()->assertJsonPath('data.is_selected', true);

        return [$connection->fresh(), $hmacSecret];
    }

    private function connectionUrl(Venue $venue): string
    {
        return "/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/receiving-connection";
    }
}
