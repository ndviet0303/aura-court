<?php

namespace App\Services\Payment;

use App\Models\ReceivingConnection;
use App\Models\ReceivingConnectionEvidence;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SePayReceivingConnectionService
{
    public function create(Venue $venue, User $owner, array $data): ReceivingConnection
    {
        return DB::transaction(function () use ($venue, $owner, $data): ReceivingConnection {
            $venue->newQuery()->whereKey($venue->id)->lockForUpdate()->firstOrFail();

            $connection = new ReceivingConnection([
                'organization_id' => $venue->organization_id,
                'venue_id' => $venue->id,
                'owner_user_id' => $owner->id,
                'provider' => 'sepay',
                'routing_id' => (string) Str::uuid(),
                'bank_code' => $data['bank_code'],
                'status' => ReceivingConnection::STATUS_DRAFT,
                'owner_attested_at' => now(),
                'credential_version' => 0,
            ]);
            $connection->setAccountDetails($data['account_name'], $data['account_number']);
            $connection->save();

            $this->recordEvidence($connection, 'connection_created', $owner->id, 0, [
                'legal_owner_confirmed' => true,
                'account_last_four' => $connection->account_last_four,
            ]);

            return $connection->refresh();
        });
    }

    public function rotateCredential(ReceivingConnection $connection, User $actor, string $secret): ReceivingConnection
    {
        return DB::transaction(function () use ($connection, $actor, $secret): ReceivingConnection {
            $locked = ReceivingConnection::query()->lockForUpdate()->findOrFail($connection->id);
            abort_unless(
                ! in_array($locked->status, [ReceivingConnection::STATUS_REVOKED, ReceivingConnection::STATUS_SUPERSEDED], true),
                409,
                'This receiving connection can no longer be changed.'
            );

            $previousVersion = (int) $locked->credential_version;
            $previousStatus = $locked->status;
            $newVersion = $previousVersion + 1;
            $locked->webhook_secret_ciphertext = Crypt::encryptString($secret);
            $locked->credential_version = $newVersion;
            $locked->status = ReceivingConnection::STATUS_PENDING_VERIFICATION;
            $locked->verified_at = null;
            $locked->effective_from = null;
            if ($previousStatus === ReceivingConnection::STATUS_VERIFIED) {
                $locked->effective_until = now();
            }
            $locked->save();

            $this->recordEvidence($locked, 'credential_rotated', $actor->id, $newVersion, [
                'previous_version' => $previousVersion,
                'previous_status' => $previousStatus,
                'credential_version' => $newVersion,
            ]);

            return $locked->refresh();
        });
    }

    public function rotateApiToken(
        ReceivingConnection $connection,
        User $actor,
        string $apiToken,
        string $providerAccountId
    ): ReceivingConnection {
        return DB::transaction(function () use ($connection, $actor, $apiToken, $providerAccountId): ReceivingConnection {
            $locked = ReceivingConnection::query()->lockForUpdate()->findOrFail($connection->id);
            abort_unless(
                ! in_array($locked->status, [ReceivingConnection::STATUS_REVOKED, ReceivingConnection::STATUS_SUPERSEDED], true),
                409,
                'This receiving connection can no longer be changed.'
            );

            $previousAccountId = $locked->provider_account_id;
            $previousVersion = (int) $locked->api_token_version;
            $newVersion = $previousVersion + 1;
            $locked->provider_account_id = $providerAccountId;
            $locked->api_token_ciphertext = Crypt::encryptString($apiToken);
            $locked->api_token_version = $newVersion;
            if ($previousAccountId !== $providerAccountId) {
                $locked->last_queried_transaction_id = null;
            }
            $locked->save();

            $this->recordEvidence($locked, 'api_token_rotated', $actor->id, $newVersion, [
                'previous_version' => $previousVersion,
                'api_token_version' => $newVersion,
                'provider_account_id_changed' => $previousAccountId !== $providerAccountId,
            ], credentialType: 'sepay_api_token');

            return $locked->refresh();
        });
    }

    public function recordSelection(ReceivingConnection $connection, User $actor): void
    {
        $this->recordEvidence($connection, 'receiver_selected', $actor->id, (int) $connection->credential_version, [
            'venue_id' => (int) $connection->venue_id,
            'selected_at' => now()->toIso8601String(),
        ], credentialType: 'webhook_hmac');
    }

    public function verifyInboundWebhook(
        ReceivingConnection $connection,
        string $timestamp,
        string $signature,
        string $rawBody
    ): array {
        abort_if(strlen($rawBody) > 65536, 413, 'Webhook payload is too large.');
        abort_unless(preg_match('/^\d{10,13}$/', $timestamp) === 1, 401, 'Invalid webhook timestamp.');

        $timestampSeconds = (int) $timestamp;
        if (strlen($timestamp) === 13) {
            $timestampSeconds = intdiv($timestampSeconds, 1000);
        }
        abort_unless(abs(now()->timestamp - $timestampSeconds) <= 300, 401, 'Webhook timestamp is outside the allowed window.');

        abort_unless(preg_match('/^sha256=[a-f0-9]{64}$/i', $signature) === 1, 401, 'Invalid webhook signature.');
        try {
            $secret = $connection->webhookSecret();
        } catch (DecryptException) {
            abort(401, 'Invalid webhook signature.');
        }
        abort_unless($secret !== null && $connection->credential_version > 0, 401, 'Invalid webhook signature.');

        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
        abort_unless(hash_equals($expected, strtolower($signature)), 401, 'Invalid webhook signature.');

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(422, 'Invalid webhook payload.');
        }

        abort_unless(is_array($payload), 422, 'Invalid webhook payload.');
        $this->validateProviderPayload($payload);

        try {
            $configuredAccount = $connection->accountNumber();
        } catch (DecryptException) {
            abort(401, 'Webhook receiver is unavailable.');
        }

        $fingerprint = hash('sha256', (string) $payload['id']);
        $payloadFingerprint = $this->payloadFingerprint($payload);
        $receiverMatches = hash_equals($configuredAccount, (string) $payload['accountNumber']);
        $credentialVersion = (int) $connection->credential_version;

        return DB::transaction(function () use ($connection, $fingerprint, $payloadFingerprint, $rawBody, $payload, $receiverMatches, $credentialVersion): array {
            $locked = ReceivingConnection::query()->lockForUpdate()->findOrFail($connection->id);
            abort_unless(
                ! in_array($locked->status, [ReceivingConnection::STATUS_REVOKED, ReceivingConnection::STATUS_SUPERSEDED], true),
                401,
                'Receiving connection is not active.'
            );
            abort_unless((int) $locked->credential_version === $credentialVersion, 401, 'Webhook credential has been rotated.');

            $eventType = $receiverMatches ? 'authenticated_provider_event' : 'authenticated_foreign_receiver_event';
            $evidence = $this->recordProviderEvidence(
                $locked,
                $eventType,
                $fingerprint,
                $payloadFingerprint,
                $credentialVersion,
                'webhook_hmac',
                $rawBody,
                $receiverMatches
            );

            $isInbound = strtolower((string) $payload['transferType']) === 'in';
            if ($receiverMatches && $isInbound && $locked->status !== ReceivingConnection::STATUS_VERIFIED) {
                $locked->status = ReceivingConnection::STATUS_VERIFIED;
                $locked->verified_at = now();
                $locked->effective_from = now();
                $locked->effective_until = null;
                $locked->save();
            }

            return [
                'payload' => $payload,
                'credential_version' => $credentialVersion,
                'evidence_id' => (int) $evidence->id,
                'event_fingerprint' => $fingerprint,
                'receiver_matches' => $receiverMatches,
                'source' => 'webhook',
            ];
        });
    }

    public function recordAuthenticatedQueryEvent(ReceivingConnection $connection, array $payload): array
    {
        $this->validateProviderPayload($payload);
        try {
            $configuredAccount = $connection->accountNumber();
        } catch (DecryptException) {
            abort(401, 'Webhook receiver is unavailable.');
        }

        $receiverMatches = hash_equals($configuredAccount, (string) $payload['accountNumber']);
        $fingerprint = hash('sha256', (string) $payload['id']);
        $payloadFingerprint = $this->payloadFingerprint($payload);
        $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $credentialVersion = (int) $connection->credential_version;
        $apiTokenVersion = (int) $connection->api_token_version;

        return DB::transaction(function () use ($connection, $payload, $receiverMatches, $fingerprint, $payloadFingerprint, $rawBody, $credentialVersion, $apiTokenVersion): array {
            $locked = ReceivingConnection::query()->lockForUpdate()->findOrFail($connection->id);
            abort_unless($locked->api_token_ciphertext !== null && $apiTokenVersion === (int) $locked->api_token_version, 409, 'SePay query credential has been rotated.');

            $eventType = $receiverMatches ? 'authenticated_query_event' : 'authenticated_foreign_query_event';
            $evidence = $this->recordProviderEvidence(
                $locked,
                $eventType,
                $fingerprint,
                $payloadFingerprint,
                $apiTokenVersion,
                'sepay_api_token',
                $rawBody,
                $receiverMatches
            );

            return [
                'payload' => $payload,
                'credential_version' => $credentialVersion,
                'evidence_id' => (int) $evidence->id,
                'event_fingerprint' => $fingerprint,
                'receiver_matches' => $receiverMatches,
                'source' => 'api_query',
            ];
        });
    }

    private function recordEvidence(
        ReceivingConnection $connection,
        string $eventType,
        ?int $actorId,
        int $credentialVersion,
        array $evidence,
        ?string $fingerprint = null,
        ?string $credentialType = null,
        ?string $payloadFingerprint = null
    ): ReceivingConnectionEvidence {
        return ReceivingConnectionEvidence::create([
            'receiving_connection_id' => $connection->id,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'credential_version' => $credentialVersion,
            'credential_type' => $credentialType,
            'event_fingerprint' => $fingerprint,
            'payload_fingerprint' => $payloadFingerprint,
            'evidence_ciphertext' => Crypt::encryptString(json_encode($evidence, JSON_THROW_ON_ERROR)),
        ]);
    }

    private function recordProviderEvidence(
        ReceivingConnection $connection,
        string $eventType,
        string $eventFingerprint,
        string $payloadFingerprint,
        int $credentialVersion,
        string $credentialType,
        string $rawBody,
        bool $receiverMatches
    ): ReceivingConnectionEvidence {
        $existing = ReceivingConnectionEvidence::query()
            ->where('receiving_connection_id', $connection->id)
            ->where('event_fingerprint', $eventFingerprint)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            if ($existing->payload_fingerprint !== null) {
                abort_unless(hash_equals($existing->payload_fingerprint, $payloadFingerprint), 409, 'Provider event identifier was reused with different transaction data.');
            } else {
                $stored = json_decode(Crypt::decryptString($existing->evidence_ciphertext), true, 512, JSON_THROW_ON_ERROR);
                $storedPayload = json_decode((string) ($stored['raw_body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
                abort_unless($this->payloadFingerprint($storedPayload) === $payloadFingerprint, 409, 'Provider event identifier was reused with different transaction data.');
                $existing->forceFill(['payload_fingerprint' => $payloadFingerprint])->save();
            }

            return $existing;
        }

        return $this->recordEvidence($connection, $eventType, null, $credentialVersion, [
            'raw_body' => $rawBody,
            'received_at' => now()->toIso8601String(),
            'receiver_matches' => $receiverMatches,
        ], $eventFingerprint, $credentialType, $payloadFingerprint);
    }

    private function validateProviderPayload(array $payload): void
    {
        abort_unless(
            isset($payload['id'], $payload['transactionDate'], $payload['accountNumber'], $payload['transferAmount'], $payload['transferType'])
                && is_scalar($payload['id'])
                && (string) $payload['id'] !== ''
                && strlen((string) $payload['id']) <= 190
                && is_string($payload['transactionDate'])
                && strlen($payload['transactionDate']) <= 64
                && is_string($payload['accountNumber'])
                && strlen($payload['accountNumber']) <= 34
                && is_string($payload['transferType'])
                && in_array(strtolower($payload['transferType']), ['in', 'out'], true)
                && is_numeric($payload['transferAmount'])
                && (float) $payload['transferAmount'] > 0,
            422,
            'Provider transaction is invalid.'
        );
    }

    private function payloadFingerprint(array $payload): string
    {
        $fields = ['id', 'transactionDate', 'accountNumber', 'code', 'transferType', 'transferAmount', 'referenceCode', 'currency'];
        $canonical = [];
        foreach ($fields as $field) {
            $canonical[$field] = $field === 'currency'
                ? strtoupper((string) ($payload[$field] ?? 'VND'))
                : ($payload[$field] ?? null);
        }

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
