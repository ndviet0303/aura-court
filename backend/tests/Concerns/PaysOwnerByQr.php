<?php

namespace Tests\Concerns;

use App\Models\OwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payment\OwnerPaymentAttemptService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/** A venue's verified owner receiving connection, and SePay bank-transfer callbacks into it. */
trait PaysOwnerByQr
{
    /** @return array{0: ReceivingConnection, 1: string} the connection and its webhook API key */
    protected function connectOwnerReceiver(Venue $venue, string $accountNumber = '0987654321'): array
    {
        $connection = new ReceivingConnection([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->id,
            'owner_user_id' => User::factory()->create()->id,
            'provider' => 'sepay',
            'routing_id' => (string) Str::uuid(),
            'bank_code' => 'MBBank',
            'status' => ReceivingConnection::STATUS_VERIFIED,
            'credential_version' => 1,
            'owner_attested_at' => now(),
            'verified_at' => now(),
            'effective_from' => now(),
        ]);
        $connection->setAccountDetails('Venue Owner', $accountNumber);
        $secret = 'whsec_'.Str::random(48);
        $connection->webhook_secret_ciphertext = Crypt::encryptString($secret);
        $connection->save();
        $venue->forceFill(['current_receiving_connection_id' => $connection->id])->save();

        return [$connection, $secret];
    }

    /** Delivers one authenticated incoming transfer and processes its receipt like the queue would. */
    protected function payOwner(ReceivingConnection $connection, string $secret, string $code, int $amount, string $eventId, ?string $transactionDate = null): OwnerPaymentReceipt
    {
        $raw = json_encode([
            'id' => $eventId,
            'transactionDate' => $transactionDate ?? now()->addSeconds(2)->toIso8601String(),
            'accountNumber' => $connection->accountNumber(),
            'code' => $code,
            'content' => $code,
            'transferType' => 'in',
            'transferAmount' => $amount,
            'referenceCode' => 'BANK-'.$eventId,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/webhooks/sepay/connections/'.$connection->routing_id, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Apikey '.$secret,
        ], $raw)->assertOk();

        $receipt = OwnerPaymentReceipt::where('provider_event_id', $eventId)->firstOrFail();
        if (! $receipt->processed_at) {
            app(OwnerPaymentAttemptService::class)->processReceipt((int) $receipt->id);
        }

        return $receipt->fresh();
    }
}
