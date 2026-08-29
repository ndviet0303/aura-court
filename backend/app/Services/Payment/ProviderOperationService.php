<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentIntentRequest;
use App\Contracts\Payment\PaymentIntentResponse;
use App\Contracts\Payment\PayoutInstruction;
use App\Contracts\Payment\PayoutSubmissionResponse;
use App\Contracts\Payment\RefundRequest;
use App\Contracts\Payment\RefundResponse;
use App\Models\PaymentIntent;
use App\Models\ProviderOperation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ProviderOperationService
{
    public function __construct(private readonly PaymentProviderManager $providers) {}

    public function createPaymentIntent(PaymentIntentRequest $request, ?int $organizationId = null, ?int $userId = null): PaymentIntentResponse
    {
        $provider = $this->providers->provider();
        $existing = PaymentIntent::query()->where('provider', $provider->name())->where('idempotency_key', $request->idempotencyKey)->first();
        if ($existing?->provider_intent_id) {
            return new PaymentIntentResponse($existing->provider, $existing->provider_intent_id, $existing->status, $existing->amount, $existing->currency, raw: $existing->metadata ?? []);
        }

        $intent = PaymentIntent::firstOrCreate([
            'provider' => $provider->name(),
            'idempotency_key' => $request->idempotencyKey,
        ], [
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'reference' => $request->reference,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'status' => 'pending',
            'metadata' => $request->metadata,
        ]);
        if ($intent->provider_intent_id) {
            return new PaymentIntentResponse($intent->provider, $intent->provider_intent_id, $intent->status, $intent->amount, $intent->currency, raw: $intent->metadata ?? []);
        }

        $response = $provider->createPaymentIntent($request);
        $intent->update(['provider_intent_id' => $response->providerIntentId, 'status' => strtolower($response->status), 'metadata' => array_merge($intent->metadata ?? [], $response->raw)]);
        return $response;
    }

    public function refund(RefundRequest $request, ?int $organizationId = null): RefundResponse
    {
        $provider = $this->providers->provider();
        $operation = $this->operation('refund', $provider->name(), $request->idempotencyKey, $organizationId, $request->reference, $request->amount);
        if ($operation->response_payload) {
            return new RefundResponse($provider->name(), (string) $operation->provider_resource_id, (string) ($operation->response_payload['status'] ?? $operation->status), $request->amount, $request->currency, $operation->response_payload);
        }
        try {
            $response = $provider->executeRefund($request);
            $this->complete($operation, $response->providerRefundId, $response->status, $response->toArray());
            return $response;
        } catch (\Throwable $exception) {
            $this->fail($operation, $exception);
            throw $exception;
        }
    }

    public function submitPayout(PayoutInstruction $instruction, ?int $organizationId = null): PayoutSubmissionResponse
    {
        $provider = $this->providers->provider();
        $operation = $this->operation('payout', $provider->name(), $instruction->idempotencyKey, $organizationId, $instruction->reference, $instruction->amount);
        if ($operation->response_payload) {
            return new PayoutSubmissionResponse($provider->name(), (string) $operation->provider_resource_id, (string) ($operation->response_payload['status'] ?? $operation->status), $instruction->amount, $instruction->currency, $operation->response_payload);
        }
        try {
            $response = $provider->submitPayout($instruction);
            $this->complete($operation, $response->providerPayoutId, $response->status, $response->toArray());
            return $response;
        } catch (\Throwable $exception) {
            $this->fail($operation, $exception);
            throw $exception;
        }
    }

    private function operation(string $type, string $provider, string $key, ?int $organizationId, ?string $reference, int $amount): ProviderOperation
    {
        try {
            return DB::transaction(fn () => ProviderOperation::firstOrCreate([
                'provider' => $provider,
                'idempotency_key' => $key,
            ], [
                'organization_id' => $organizationId,
                'type' => $type,
                'reference' => $reference,
                'status' => 'pending',
                'request_payload' => ['amount' => $amount],
                'next_attempt_at' => now(),
            ]));
        } catch (QueryException $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'provider_operations')) {
                throw $exception;
            }
            return ProviderOperation::where('provider', $provider)->where('idempotency_key', $key)->firstOrFail();
        }
    }

    private function complete(ProviderOperation $operation, string $resourceId, string $status, array $response): void
    {
        $operation->update([
            'provider_resource_id' => $resourceId,
            'status' => strtolower($status),
            'response_payload' => $response,
            'attempts' => $operation->attempts + 1,
            'submitted_at' => $operation->submitted_at ?? now(),
            'completed_at' => in_array(strtolower($status), ['paid', 'completed', 'succeeded', 'failed', 'reversed'], true) ? now() : null,
            'next_attempt_at' => null,
            'last_error' => null,
        ]);
    }

    private function fail(ProviderOperation $operation, \Throwable $exception): void
    {
        $attempts = $operation->attempts + 1;
        $operation->update([
            'status' => 'retryable_failure',
            'attempts' => $attempts,
            'last_error' => $exception->getMessage(),
            'next_attempt_at' => now()->addSeconds(min(3600, 2 ** min($attempts, 10))),
        ]);
    }
}
