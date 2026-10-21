<?php

namespace App\Services\Payment;

use App\Models\OwnerPaymentAllocation;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use App\Models\ReceivingConnectionEvidence;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Booking\RecurringSeriesService;
use App\Services\Finance\FinancialPostingService;
use App\Services\POS\PosQrPaymentService;
use App\Services\Tournament\TournamentRegistrationPaymentService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class OwnerPaymentAttemptService
{
    public function __construct(
        private readonly FinancialPostingService $financials,
        private readonly OwnerPaymentExceptionRecorder $exceptions,
    ) {}

    private const PURPOSES = ['booking', 'recurring_period', 'split_share', 'pos_order', 'tournament_registration'];

    /** Purposes confirmed by their own service: [service, check that locks and re-reads it, exception when it is gone]. */
    private const CONFIRMERS = [
        'recurring_period' => [RecurringSeriesService::class, 'hasConfirmablePeriod', 'recurring_hold_unavailable'],
        'split_share' => [SplitPaymentService::class, 'hasConfirmableShare', 'split_share_unavailable'],
        'pos_order' => [PosQrPaymentService::class, 'hasConfirmableOrder', 'pos_order_unavailable'],
        'tournament_registration' => [TournamentRegistrationPaymentService::class, 'hasConfirmableRegistration', 'tournament_registration_unavailable'],
    ];

    public function createAttempt(
        ReceivingConnection $connection,
        ?User $payer,
        string $purpose,
        string $purposeId,
        string $reference,
        int $amount,
        string $currency,
        CarbonInterface $expiresAt,
        string $idempotencyKey
    ): OwnerPaymentAttempt {
        $purpose = strtolower(trim($purpose));
        $reference = strtoupper(trim($reference));
        $currency = strtoupper(trim($currency));
        abort_unless(in_array($purpose, self::PURPOSES, true), 422, 'Unsupported owner-payment purpose.');
        abort_unless($purposeId !== '' && strlen($purposeId) <= 120, 422, 'Invalid payment purpose reference.');
        abort_unless($reference !== '' && strlen($reference) <= 120, 422, 'Invalid payment reference.');
        abort_unless($idempotencyKey !== '' && strlen($idempotencyKey) <= 255, 422, 'Invalid idempotency key.');
        abort_unless($amount > 0, 422, 'Payment amount must be greater than zero.');
        abort_unless($currency === 'VND', 422, 'SePay owner connections currently accept VND only.');
        abort_unless($expiresAt->isFuture(), 422, 'Payment attempt expiry must be in the future.');

        $canonicalRequest = [
            'receiving_connection_id' => $connection->id,
            'user_id' => $payer?->id,
            'purpose' => $purpose,
            'purpose_id' => $purposeId,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
        $requestFingerprint = hash('sha256', json_encode($canonicalRequest, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($connection, $payer, $purpose, $purposeId, $reference, $amount, $currency, $expiresAt, $idempotencyKey, $requestFingerprint): OwnerPaymentAttempt {
                $lockedConnection = ReceivingConnection::query()->lockForUpdate()->findOrFail($connection->id);
                $existing = OwnerPaymentAttempt::query()
                    ->where('organization_id', $lockedConnection->organization_id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    abort_unless(hash_equals($existing->request_fingerprint, $requestFingerprint), 409, 'Idempotency key was already used for a different payment attempt.');

                    return $existing;
                }

                $this->assertConnectionCanCollect($lockedConnection);

                $receiverSnapshot = $this->receiverSnapshot($lockedConnection, (int) $lockedConnection->credential_version);
                $purposeSnapshot = [
                    'payment_model' => 'direct_owner_v1',
                    'purpose' => $purpose,
                    'purpose_id' => $purposeId,
                    'reference' => $reference,
                    'amount' => $amount,
                    'currency' => $currency,
                    'organization_id' => (int) $lockedConnection->organization_id,
                    'venue_id' => (int) $lockedConnection->venue_id,
                    'expires_at' => $expiresAt->toIso8601String(),
                ];

                return OwnerPaymentAttempt::create([
                    'organization_id' => $lockedConnection->organization_id,
                    'venue_id' => $lockedConnection->venue_id,
                    'user_id' => $payer?->id,
                    'receiving_connection_id' => $lockedConnection->id,
                    'payment_model' => 'direct_owner_v1',
                    'provider' => $lockedConnection->provider,
                    'purpose' => $purpose,
                    'purpose_id' => $purposeId,
                    'reference' => $reference,
                    'idempotency_key' => $idempotencyKey,
                    'request_fingerprint' => $requestFingerprint,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => OwnerPaymentAttempt::STATUS_PENDING,
                    'credential_version' => $lockedConnection->credential_version,
                    'receiver_snapshot_ciphertext' => $this->encryptSnapshot($receiverSnapshot),
                    'purpose_snapshot_ciphertext' => $this->encryptSnapshot($purposeSnapshot),
                    'expires_at' => $expiresAt,
                ]);
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicateAttempt($exception)) {
                throw $exception;
            }

            $existing = OwnerPaymentAttempt::query()
                ->where('organization_id', $connection->organization_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if (! $existing) {
                if (str_contains(strtolower($exception->getMessage()), 'reference')) {
                    abort(409, 'Payment reference is already in use.');
                }

                throw $exception;
            }
            abort_unless(hash_equals($existing->request_fingerprint, $requestFingerprint), 409, 'Idempotency key was already used for a different payment attempt.');

            return $existing;
        }
    }

    public function latestForPurpose(string $purpose, string $purposeId): ?OwnerPaymentAttempt
    {
        return OwnerPaymentAttempt::query()
            ->where('purpose', strtolower(trim($purpose)))
            ->where('purpose_id', $purposeId)
            ->latest('id')
            ->first();
    }

    /** Expire every still-pending attempt for a purpose/reference (used when a period is released). */
    public function expirePendingForPurpose(string $purpose, string $purposeId): int
    {
        return OwnerPaymentAttempt::query()
            ->withoutGlobalScope('tenant_isolation')
            ->where('purpose', strtolower(trim($purpose)))
            ->where('purpose_id', $purposeId)
            ->where('status', OwnerPaymentAttempt::STATUS_PENDING)
            ->update(['status' => OwnerPaymentAttempt::STATUS_EXPIRED]);
    }

    /** @param array{payload: array, credential_version: int, evidence_id: int, event_fingerprint: string, receiver_matches: bool} $verified */
    public function receiveVerifiedEvent(ReceivingConnection $connection, array $verified): OwnerPaymentReceipt
    {
        $payload = $verified['payload'];
        $providerEventId = (string) $payload['id'];

        return DB::transaction(function () use ($connection, $verified, $payload, $providerEventId): OwnerPaymentReceipt {
            $lockedConnection = ReceivingConnection::query()->lockForUpdate()->findOrFail($connection->id);
            $existing = OwnerPaymentReceipt::query()
                ->where('receiving_connection_id', $lockedConnection->id)
                ->where('provider', $lockedConnection->provider)
                ->where('provider_event_id', $providerEventId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $transferType = strtolower((string) ($payload['transferType'] ?? ''));
            $amountValue = filter_var($payload['transferAmount'] ?? null, FILTER_VALIDATE_INT);
            $amount = $amountValue === false ? 0 : (int) $amountValue;
            $eventCurrency = strtoupper(trim((string) ($payload['currency'] ?? 'VND')));
            $eventAt = $this->parseEventTime($payload['transactionDate'] ?? null);
            $paymentCode = $this->scalarString($payload['code'] ?? null);
            $referenceCode = $this->scalarString($payload['referenceCode'] ?? null);

            $receiverSnapshot = $this->receiverSnapshot($lockedConnection, (int) $verified['credential_version']);

            return OwnerPaymentReceipt::create([
                'organization_id' => $lockedConnection->organization_id,
                'venue_id' => $lockedConnection->venue_id,
                'receiving_connection_id' => $lockedConnection->id,
                'receiving_connection_evidence_id' => $verified['evidence_id'],
                'provider' => $lockedConnection->provider,
                'source' => $verified['source'] ?? 'webhook',
                'provider_event_id' => $providerEventId,
                'event_fingerprint' => $verified['event_fingerprint'],
                'payment_attempt_id' => null,
                'purpose' => null,
                'purpose_id' => null,
                'payment_code' => $paymentCode === '' ? null : substr($paymentCode, 0, 120),
                'reference_code' => $referenceCode === '' ? null : substr($referenceCode, 0, 190),
                'transfer_type' => substr($transferType, 0, 8),
                'amount' => $amount,
                'currency' => preg_match('/^[A-Z]{3}$/', $eventCurrency) === 1 ? $eventCurrency : 'XXX',
                'event_at' => $eventAt,
                'credential_version' => (int) $verified['credential_version'],
                'status' => OwnerPaymentReceipt::STATUS_PENDING,
                'exception_code' => null,
                'receiver_snapshot_ciphertext' => $this->encryptSnapshot($receiverSnapshot),
                'purpose_snapshot_ciphertext' => null,
                'received_at' => now(),
                'processing_attempts' => 0,
                'next_attempt_at' => now(),
            ]);
        });
    }

    public function claimReceiptForDispatch(int $receiptId, int $leaseSeconds = 60): bool
    {
        return DB::transaction(function () use ($receiptId, $leaseSeconds): bool {
            $receipt = OwnerPaymentReceipt::query()->lockForUpdate()->findOrFail($receiptId);
            if ($receipt->processed_at || $receipt->status !== OwnerPaymentReceipt::STATUS_PENDING) {
                return false;
            }
            if ($receipt->next_attempt_at?->isFuture()) {
                return false;
            }

            $receipt->forceFill(['next_attempt_at' => now()->addSeconds(max(1, $leaseSeconds))])->save();

            return true;
        });
    }

    public function processReceipt(int $receiptId): void
    {
        DB::transaction(function () use ($receiptId): void {
            $receipt = OwnerPaymentReceipt::query()->lockForUpdate()->findOrFail($receiptId);
            if ($receipt->processed_at || $receipt->status !== OwnerPaymentReceipt::STATUS_PENDING) {
                return;
            }

            $receipt->processing_attempts = (int) $receipt->processing_attempts + 1;
            $receipt->next_attempt_at = null;
            $receipt->save();

            $evidence = ReceivingConnectionEvidence::query()->findOrFail($receipt->receiving_connection_evidence_id);
            $evidenceData = json_decode(Crypt::decryptString($evidence->evidence_ciphertext), true, 512, JSON_THROW_ON_ERROR);
            $rawBody = (string) ($evidenceData['raw_body'] ?? '');
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            $receiverMatches = (bool) ($evidenceData['receiver_matches'] ?? false);
            abort_unless(
                is_array($payload)
                    && (string) ($payload['id'] ?? '') === (string) $receipt->provider_event_id
                    && hash('sha256', (string) ($payload['id'] ?? '')) === $receipt->event_fingerprint,
                409,
                'Stored provider event identity does not match its receipt.'
            );

            $connection = ReceivingConnection::query()->findOrFail($receipt->receiving_connection_id);
            $paymentCode = $receipt->payment_code ?? '';
            $attempt = null;
            $purposeSnapshot = null;
            if ($receiverMatches && $paymentCode !== '') {
                $attempt = OwnerPaymentAttempt::query()
                    ->where('receiving_connection_id', $connection->id)
                    ->where('reference', strtoupper($paymentCode))
                    ->lockForUpdate()
                    ->first();
                $purposeSnapshot = $attempt?->purposeSnapshot();
            }

            $status = OwnerPaymentReceipt::STATUS_UNMATCHED;
            $exceptionCode = null;
            $bookingReservation = null;
            $confirmer = null;
            if (! $receiverMatches) {
                $status = OwnerPaymentReceipt::STATUS_EXCEPTION;
                $exceptionCode = 'foreign_receiver';
            } elseif ($receipt->transfer_type !== 'in') {
                $status = OwnerPaymentReceipt::STATUS_EXCEPTION;
                $exceptionCode = 'not_inbound';
            } elseif ((int) $receipt->amount <= 0) {
                $status = OwnerPaymentReceipt::STATUS_EXCEPTION;
                $exceptionCode = 'invalid_amount';
            } elseif (! preg_match('/^[A-Z]{3}$/', (string) $receipt->currency)) {
                $status = OwnerPaymentReceipt::STATUS_EXCEPTION;
                $exceptionCode = 'invalid_currency';
            } elseif ($receipt->currency !== 'VND') {
                $status = OwnerPaymentReceipt::STATUS_EXCEPTION;
                $exceptionCode = 'currency_mismatch';
            } elseif (! $receipt->event_at) {
                $status = OwnerPaymentReceipt::STATUS_EXCEPTION;
                $exceptionCode = 'invalid_transaction_time';
            } elseif ($paymentCode === '') {
                $status = OwnerPaymentReceipt::STATUS_UNMATCHED;
                $exceptionCode = 'missing_payment_code';
            } elseif (! $attempt) {
                $status = OwnerPaymentReceipt::STATUS_UNMATCHED;
                $exceptionCode = 'unmatched_reference';
            } else {
                $this->applyAttemptChecks($attempt, $receipt->event_at, $receipt->currency, (int) $receipt->amount, $status, $exceptionCode);
                if ($status === OwnerPaymentReceipt::STATUS_ALLOCATED && $attempt->purpose === 'booking') {
                    $bookingReservation = Reservation::query()
                        ->withoutGlobalScope('tenant_isolation')
                        ->where('organization_id', $attempt->organization_id)
                        ->where('code', $attempt->purpose_id)
                        ->lockForUpdate()
                        ->first();
                    if (! $bookingReservation
                        || (int) $bookingReservation->user_id !== (int) $attempt->user_id
                        || $bookingReservation->payment_model !== 'direct_owner_v1'
                        || $bookingReservation->status !== 'held') {
                        $bookingReservation = null;
                        $status = OwnerPaymentReceipt::STATUS_EXCEPTION;
                        $exceptionCode = 'reservation_hold_unavailable';
                    }
                } elseif ($status === OwnerPaymentReceipt::STATUS_ALLOCATED && isset(self::CONFIRMERS[$attempt->purpose])) {
                    [$service, $check, $unavailable] = self::CONFIRMERS[$attempt->purpose];
                    $confirmer = app($service);
                    if (! $confirmer->{$check}($attempt)) {
                        $confirmer = null;
                        $status = OwnerPaymentReceipt::STATUS_EXCEPTION;
                        $exceptionCode = $unavailable;
                    }
                }
            }

            $receipt->forceFill([
                'payment_attempt_id' => $attempt?->id,
                'purpose' => $attempt?->purpose,
                'purpose_id' => $attempt?->purpose_id,
                'purpose_snapshot_ciphertext' => $purposeSnapshot ? $this->encryptSnapshot($purposeSnapshot) : null,
                'status' => $status,
                'exception_code' => $exceptionCode,
                'processed_at' => now(),
                'processing_error' => null,
                'next_attempt_at' => null,
            ])->save();

            if ($status === OwnerPaymentReceipt::STATUS_ALLOCATED && $attempt !== null && $purposeSnapshot !== null) {
                $allocation = OwnerPaymentAllocation::create([
                    'organization_id' => $attempt->organization_id,
                    'venue_id' => $attempt->venue_id,
                    'receiving_connection_id' => $connection->id,
                    'payment_attempt_id' => $attempt->id,
                    'payment_receipt_id' => $receipt->id,
                    'purpose' => $attempt->purpose,
                    'purpose_id' => $attempt->purpose_id,
                    'amount' => (int) $receipt->amount,
                    'currency' => $receipt->currency,
                    'credential_version' => (int) $receipt->credential_version,
                    'receiver_snapshot_ciphertext' => $attempt->receiver_snapshot_ciphertext,
                    'purpose_snapshot_ciphertext' => $attempt->purpose_snapshot_ciphertext,
                    'allocated_at' => now(),
                ]);

                $attempt->forceFill([
                    'status' => OwnerPaymentAttempt::STATUS_PAID,
                    'paid_at' => $receipt->event_at,
                ])->save();
                if ($bookingReservation) {
                    $bookingReservation->forceFill([
                        'status' => 'confirmed',
                        'confirmed_at' => $receipt->event_at,
                    ])->save();
                    $bookingReservation->slots()->where('status', 'held')->update(['status' => 'confirmed']);
                    $bookingReservation->entitlements()->where('status', 'held')->update(['status' => 'active']);
                }
                $confirmer?->confirmPaidAttempt($allocation, $receipt->event_at);
                $this->financials->postDirectOwnerReceipt($receipt);
            } elseif ($exceptionCode !== null) {
                $this->exceptions->record($receipt, $exceptionCode);
            }
        });
    }

    public function markReceiptProcessingFailure(int $receiptId, \Throwable $exception): void
    {
        DB::transaction(function () use ($receiptId, $exception): void {
            $receipt = OwnerPaymentReceipt::query()->lockForUpdate()->findOrFail($receiptId);
            if ($receipt->processed_at) {
                return;
            }

            $attempts = (int) $receipt->processing_attempts + 1;
            $exhausted = $attempts >= 8;
            $exceptionCode = $exhausted ? 'processing_exhausted' : $receipt->exception_code;
            $receipt->forceFill([
                'processing_attempts' => $attempts,
                'processing_error' => get_class($exception),
                'next_attempt_at' => $exhausted ? null : now()->addSeconds(min(3600, 2 ** min($attempts, 10))),
                'status' => $exhausted ? OwnerPaymentReceipt::STATUS_EXCEPTION : OwnerPaymentReceipt::STATUS_PENDING,
                'exception_code' => $exceptionCode,
                'processed_at' => $exhausted ? now() : null,
            ])->save();

            if ($exhausted) {
                $this->exceptions->record($receipt, 'processing_exhausted');
            }
        });
    }

    private function applyAttemptChecks(
        OwnerPaymentAttempt $attempt,
        Carbon $eventAt,
        string $eventCurrency,
        int $amount,
        string &$status,
        ?string &$exceptionCode
    ): void {
        if ($eventAt->lt($attempt->created_at)) {
            $status = 'exception';
            $exceptionCode = 'transfer_before_attempt';
        } elseif ($eventAt->gt($attempt->expires_at) || $attempt->status === OwnerPaymentAttempt::STATUS_EXPIRED) {
            $status = 'late';
            $exceptionCode = 'transfer_after_expiry';
        } elseif (! in_array($attempt->purpose, self::PURPOSES, true)) {
            $status = 'exception';
            $exceptionCode = 'unsupported_purpose';
        } elseif ($attempt->status !== OwnerPaymentAttempt::STATUS_PENDING) {
            $status = 'exception';
            $exceptionCode = 'attempt_not_pending';
        } elseif ($attempt->currency !== $eventCurrency) {
            $status = 'exception';
            $exceptionCode = 'currency_mismatch';
        } elseif ($amount < (int) $attempt->amount) {
            $status = 'exception';
            $exceptionCode = 'partial_amount';
        } elseif ($amount > (int) $attempt->amount) {
            $status = 'exception';
            $exceptionCode = 'excess_amount';
        } else {
            $status = 'allocated';
        }
    }

    private function assertConnectionCanCollect(ReceivingConnection $connection): void
    {
        abort_unless(
            $connection->status === ReceivingConnection::STATUS_VERIFIED
                && $connection->isSelectedForVenue()
                && $connection->webhook_secret_ciphertext !== null
                && $connection->verified_at !== null
                && ($connection->effective_until === null || $connection->effective_until->isFuture()),
            409,
            'A verified receiving connection must be selected for this venue before creating a payment attempt.'
        );
    }

    private function receiverSnapshot(ReceivingConnection $connection, int $credentialVersion): array
    {
        return [
            'receiving_connection_id' => (int) $connection->id,
            'routing_id' => $connection->routing_id,
            'provider' => $connection->provider,
            'bank_code' => $connection->bank_code,
            'account_name' => $connection->accountName(),
            'account_number' => $connection->accountNumber(),
            'account_last_four' => $connection->account_last_four,
            'credential_version' => $credentialVersion,
        ];
    }

    private function encryptSnapshot(array $snapshot): string
    {
        return Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function parseEventTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function isDuplicateAttempt(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'owner_payment_attempts')
            && (str_contains($message, 'unique') || str_contains($message, 'duplicate'));
    }
}
