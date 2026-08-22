<?php

namespace App\Services;

use App\Contracts\Payment\WebhookEvent;
use App\Jobs\ProcessProviderEvent;
use App\Models\ProviderEvent;
use App\Services\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderWebhookService
{
    public function __construct(
        private readonly PaymentProviderManager $providers,
        private readonly TenantContext $tenantContext,
    ) {}

    public function receive(string $providerName, Request $request): array
    {
        $provider = $this->providers->provider($providerName);
        $raw = $request->getContent();
        $signature = (string) ($request->header('X-Signature') ?? $request->header('X-PayOS-Signature') ?? '');
        if (! $provider->verifyWebhookSignature($raw, $signature, $request->headers->all())) {
            app(ObservabilityService::class)->alert('webhook_verification_failed', ['provider' => $providerName]);
            abort(401, 'Invalid webhook signature.');
        }
        $event = $provider->parseWebhookEvent($raw, $request->headers->all());

        try {
            $record = DB::transaction(function () use ($event): ProviderEvent {
                $existing = ProviderEvent::query()
                    ->where('provider', $event->provider)
                    ->where('event_id', $event->eventId)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    return $existing;
                }

                return ProviderEvent::create([
                    'organization_id' => $this->tenantContext->getOrganizationId(),
                    'provider' => $event->provider,
                    'event_id' => $event->eventId,
                    'type' => $event->type,
                    'status' => $event->status,
                    'reference' => $event->reference,
                    'provider_payment_id' => $event->providerPaymentId,
                    'amount' => $event->amount,
                    'currency' => $event->currency,
                    'payload' => $event->data,
                    'verified_at' => now(),
                    'next_attempt_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicate($exception)) {
                throw $exception;
            }
            $record = ProviderEvent::query()->where('provider', $event->provider)->where('event_id', $event->eventId)->firstOrFail();
        }

        if ($record->processed_at) {
            app(ObservabilityService::class)->metric('webhook.duplicate', 1, ['provider' => $providerName]);
            return ['duplicate' => true, 'eventId' => $record->event_id, 'status' => $record->status];
        }

        ProcessProviderEvent::dispatch($record->id);

        return ['duplicate' => false, 'eventId' => $record->event_id, 'status' => $record->status];
    }

    public function markProcessed(ProviderEvent $event): void
    {
        $event->forceFill(['processed_at' => now(), 'processing_error' => null])->save();
    }

    public function markFailed(ProviderEvent $event, \Throwable $exception): void
    {
        $attempts = $event->attempts + 1;
        $event->forceFill([
            'attempts' => $attempts,
            'processing_error' => $exception->getMessage(),
            'next_attempt_at' => now()->addSeconds(min(3600, 2 ** min($attempts, 10))),
        ])->save();
    }

    private function isDuplicate(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return str_contains($message, 'provider_events') && (str_contains($message, 'unique') || str_contains($message, 'duplicate'));
    }
}
