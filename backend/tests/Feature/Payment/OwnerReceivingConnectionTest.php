<?php

namespace Tests\Feature\Payment;

use App\Models\OwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use App\Models\Venue;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerReceivingConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_credentials_are_encrypted_and_never_returned_or_logged(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $venue = $fixture['org1']['venue1'];
        Sanctum::actingAs($owner);
        Log::spy();

        $created = $this->postJson($this->connectionUrl($venue), [
            'bank_code' => 'MBBank',
            'account_name' => 'Aura Court Owner',
            'account_number' => '0123456789',
            'legal_owner_confirmed' => true,
        ])->assertCreated()
            ->assertJsonPath('data.status', ReceivingConnection::STATUS_DRAFT)
            ->assertJsonPath('data.account_number_masked', '****6789')
            ->assertJsonPath('data.onboarding.authentication', 'Api_Key')
            ->assertJsonPath('data.onboarding.authorization_header', 'Authorization: Apikey <key>')
            ->assertJsonPath('data.onboarding.event', 'In_only')
            ->assertJsonPath('data.onboarding.payment_code.prefix', 'AC')
            ->assertJsonPath('data.onboarding.payment_code.suffix_min_length', 18)
            ->assertJsonPath('data.onboarding.payment_code.suffix_max_length', 18)
            ->assertJsonPath('data.onboarding.payment_code.suffix_type', 'NumberAndLetter')
            ->assertJsonMissingPath('data.account_number')
            ->assertJsonMissingPath('data.account_number_ciphertext')
            ->assertJsonMissingPath('data.webhook_secret_ciphertext');

        $connectionId = (int) $created->json('data.id');
        $connection = ReceivingConnection::findOrFail($connectionId);
        $this->assertStringEndsWith('/api/v1/webhooks/sepay/connections/'.$connection->routing_id, $created->json('data.webhook_url'));
        $this->assertSame('0123456789', $connection->accountNumber());
        $this->assertNotSame('0123456789', $connection->account_number_ciphertext);
        $serialized = $connection->toArray();
        $this->assertArrayNotHasKey('account_number_ciphertext', $serialized);
        $this->assertArrayNotHasKey('webhook_secret_ciphertext', $serialized);
        $this->assertArrayNotHasKey('account_number', $serialized);

        $secret = 'whsec_'.Str::random(48);
        $response = $this->postJson($this->connectionUrl($venue)."/{$connectionId}/credential", [
            'webhook_api_key' => $secret,
        ])->assertOk()
            ->assertJsonPath('data.status', ReceivingConnection::STATUS_PENDING_VERIFICATION)
            ->assertJsonPath('data.credential_version', 1)
            ->assertJsonMissingPath('data.webhook_secret')
            ->assertJsonMissingPath('data.webhook_secret_ciphertext')
            ->assertDontSee($secret);

        $connection->refresh();
        $this->assertNotSame($secret, $connection->webhook_secret_ciphertext);
        $this->assertSame($secret, Crypt::decryptString($connection->webhook_secret_ciphertext));
        $this->assertSame(1, $connection->credential_version);

        $this->getJson($this->connectionUrl($venue)."/{$connectionId}/evidence")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertDontSee($secret)
            ->assertJsonMissingPath('data.0.evidence_ciphertext');

        Sanctum::actingAs($fixture['org2']['owner']);
        $this->getJson($this->connectionUrl($venue)."/{$connectionId}/evidence")->assertForbidden();

        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
    }

    public function test_rotation_invalidates_old_secret_and_authenticated_event_is_private_and_idempotent(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $venue = $fixture['org1']['venue1'];
        Sanctum::actingAs($owner);

        $connection = $this->createConnection($venue);
        $firstSecret = 'whsec_'.Str::random(48);
        $secondSecret = 'whsec_'.Str::random(48);
        $this->postJson($this->connectionUrl($venue)."/{$connection->id}/credential", [
            'webhook_api_key' => $firstSecret,
        ])->assertOk();
        $this->postJson($this->connectionUrl($venue)."/{$connection->id}/credential", [
            'webhook_api_key' => $secondSecret,
        ])->assertOk()->assertJsonPath('data.credential_version', 2);

        $payload = [
            'id' => 'test-transfer-1',
            'transactionDate' => now()->toIso8601String(),
            'accountNumber' => '0123456789',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'AURA-TEST-1',
        ];
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $endpoint = '/api/v1/webhooks/sepay/connections/'.$connection->routing_id;

        $this->sendWebhook($endpoint, $rawBody, 'Apikey '.$firstSecret)->assertUnauthorized();
        $this->assertSame(ReceivingConnection::STATUS_PENDING_VERIFICATION, $connection->fresh()->status);

        $foreignPayload = [...$payload, 'id' => 'foreign-test-transfer-1', 'accountNumber' => '9999999999'];
        $foreignBody = json_encode($foreignPayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->sendWebhook($endpoint, $foreignBody, 'Apikey '.$secondSecret)->assertOk();
        $this->assertSame(ReceivingConnection::STATUS_PENDING_VERIFICATION, $connection->fresh()->status);

        $this->sendWebhook($endpoint, $rawBody, 'Apikey '.$secondSecret)->assertOk()
            ->assertExactJson(['success' => true]);
        $this->sendWebhook($endpoint, $rawBody, 'Apikey '.$secondSecret)->assertOk()
            ->assertExactJson(['success' => true]);

        $connection->refresh();
        $this->assertSame(ReceivingConnection::STATUS_VERIFIED, $connection->status);
        $this->assertSame(2, $connection->credential_version);
        $this->assertSame(1, $connection->evidence()->where('event_type', 'authenticated_provider_event')->count());
        $this->assertSame(1, OwnerPaymentReceipt::where('provider_event_id', 'test-transfer-1')->count());

        $evidence = $connection->evidence()->where('event_type', 'authenticated_provider_event')->firstOrFail();
        $this->assertSame('webhook_api_key', $evidence->credential_type);
        $storedEvidence = json_decode(Crypt::decryptString($evidence->evidence_ciphertext), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($rawBody, $storedEvidence['raw_body']);
        $this->assertStringNotContainsString($rawBody, $evidence->evidence_ciphertext);
        $receipt = OwnerPaymentReceipt::where('provider_event_id', 'test-transfer-1')->firstOrFail();
        $this->assertSame('unmatched', $receipt->status);
        $this->assertSame(0, $receipt->allocation()->count());

        $this->getJson($this->connectionUrl($venue)."/{$connection->id}/evidence")
            ->assertOk()
            ->assertDontSee($rawBody)
            ->assertDontSee($secondSecret)
            ->assertJsonMissingPath('data.0.evidence_ciphertext');
    }

    public function test_foreign_owner_cannot_manage_connection_and_bank_password_is_rejected(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $venue = $fixture['org1']['venue1'];
        $owner = $fixture['org1']['owner'];
        Sanctum::actingAs($owner);

        $this->postJson($this->connectionUrl($venue), [
            'bank_code' => 'MBBank',
            'account_name' => 'Aura Court Owner',
            'account_number' => '0123456789',
            'legal_owner_confirmed' => false,
        ])->assertUnprocessable();

        $this->postJson($this->connectionUrl($venue), [
            'bank_code' => 'MBBank',
            'account_name' => 'Aura Court Owner',
            'account_number' => '0123456789',
            'legal_owner_confirmed' => true,
            'bank_password' => 'never-store-this',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('receiving_connections', 0);

        Sanctum::actingAs($fixture['org2']['owner']);
        $this->getJson($this->connectionUrl($venue))->assertForbidden();
    }

    public function test_missing_or_wrong_api_key_is_rejected_without_evidence_or_receipt(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $venue = $fixture['org1']['venue1'];
        Sanctum::actingAs($fixture['org1']['owner']);
        $connection = $this->createConnection($venue);
        $secret = 'whsec_'.Str::random(48);
        $this->postJson($this->connectionUrl($venue)."/{$connection->id}/credential", ['webhook_api_key' => $secret])->assertOk();
        $evidenceBefore = $connection->evidence()->count();
        $endpoint = '/api/v1/webhooks/sepay/connections/'.$connection->routing_id;
        $rawBody = json_encode([
            'id' => 'auth-probe-1',
            'transactionDate' => now()->toIso8601String(),
            'accountNumber' => '0123456789',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'AURA-AUTH-1',
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        Log::spy();

        $this->sendWebhook($endpoint, $rawBody, null)->assertUnauthorized();
        $this->sendWebhook($endpoint, $rawBody, 'Apikey wrong_'.Str::random(40))->assertUnauthorized();
        $this->sendWebhook($endpoint, $rawBody, 'Bearer '.$secret)->assertUnauthorized();
        $this->sendWebhook($endpoint, $rawBody, 'Apikey '.$secret.'x')->assertUnauthorized();

        $this->assertSame($evidenceBefore, $connection->evidence()->count());
        $this->assertDatabaseMissing('owner_payment_receipts', ['provider_event_id' => 'auth-probe-1']);
        $this->assertSame(ReceivingConnection::STATUS_PENDING_VERIFICATION, $connection->fresh()->status);
        foreach (['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'] as $level) {
            Log::shouldNotHaveReceived($level);
        }
    }

    public function test_correct_key_with_foreign_account_number_matches_no_obligation(): void
    {
        $fixture = $this->createMultiOrgVenueFixture();
        $owner = $fixture['org1']['owner'];
        $venue = $fixture['org1']['venue1'];
        Sanctum::actingAs($owner);
        $connection = $this->createConnection($venue);
        $secret = 'whsec_'.Str::random(48);
        $this->postJson($this->connectionUrl($venue)."/{$connection->id}/credential", ['webhook_api_key' => $secret])->assertOk();
        $endpoint = '/api/v1/webhooks/sepay/connections/'.$connection->routing_id;
        $verify = ['id' => 'verify-foreign-1', 'transactionDate' => now()->toIso8601String(), 'accountNumber' => '0123456789', 'transferType' => 'in', 'transferAmount' => 1000, 'referenceCode' => 'VERIFY-1'];
        $this->sendWebhook($endpoint, json_encode($verify, JSON_THROW_ON_ERROR), 'Apikey '.$secret)->assertOk();
        $this->assertSame(ReceivingConnection::STATUS_VERIFIED, $connection->fresh()->status);
        $this->postJson($this->connectionUrl($venue)."/{$connection->id}/select")->assertOk();

        $attempt = app(OwnerPaymentAttemptService::class)->createAttempt(
            $connection->fresh(),
            $owner,
            'booking',
            'booking-foreign-1',
            'ACFOREIGN0000000001',
            100000,
            'VND',
            now()->addMinutes(10),
            'checkout-foreign-1'
        );
        $foreign = [
            'id' => 'foreign-account-1',
            'transactionDate' => now()->addSeconds(5)->toIso8601String(),
            'accountNumber' => '9999999999',
            'code' => 'ACFOREIGN0000000001',
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'BANK-FOREIGN-1',
        ];
        $this->sendWebhook($endpoint, json_encode($foreign, JSON_THROW_ON_ERROR), 'Apikey '.$secret)->assertOk();

        $receipt = OwnerPaymentReceipt::where('provider_event_id', 'foreign-account-1')->firstOrFail();
        $this->assertSame('foreign_receiver', $receipt->exception_code);
        $this->assertSame(0, $receipt->allocation()->count());
        $this->assertSame('pending', $attempt->fresh()->status);
    }

    private function createConnection(Venue $venue): ReceivingConnection
    {
        $response = $this->postJson($this->connectionUrl($venue), [
            'bank_code' => 'MBBank',
            'account_name' => 'Aura Court Owner',
            'account_number' => '0123456789',
            'legal_owner_confirmed' => true,
        ])->assertCreated();

        return ReceivingConnection::findOrFail((int) $response->json('data.id'));
    }

    private function sendWebhook(string $url, string $rawBody, ?string $authorization)
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($authorization !== null) {
            $server['HTTP_AUTHORIZATION'] = $authorization;
        }
        $response = $this->call('POST', $url, [], [], [], $server, $rawBody);

        if ($response->isSuccessful()) {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            $receipt = OwnerPaymentReceipt::where('provider_event_id', (string) $payload['id'])->first();
            if ($receipt && ! $receipt->processed_at) {
                app(OwnerPaymentAttemptService::class)->processReceipt($receipt->id);
            }
        }

        return $response;
    }

    private function connectionUrl(Venue $venue): string
    {
        return "/api/partner/organizations/{$venue->organization_id}/venues/{$venue->id}/receiving-connection";
    }
}
