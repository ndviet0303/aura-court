<?php

namespace App\Services;

use App\Events\TenantRealtimeEvent;
use App\Models\OutboxMessage;
use App\Models\User;
use App\Notifications\OutboxNotification;
use Illuminate\Support\Facades\Redis;

class OutboxDispatcher
{
    public function dispatch(OutboxMessage $message): void
    {
        $payload = $message->payload ?? [];
        match ($message->channel) {
            'notification' => $this->notification($payload),
            'realtime' => $this->realtime($message, $payload),
            'provider' => app(ProviderOutboxHandler::class)->handle($message),
            default => throw new \InvalidArgumentException("Unsupported outbox channel [{$message->channel}]."),
        };
    }

    private function notification(array $payload): void
    {
        $user = User::findOrFail((int) ($payload['user_id'] ?? 0));
        $user->notify(new OutboxNotification($payload['notification'] ?? $payload));
    }

    private function realtime(OutboxMessage $message, array $payload): void
    {
        $organizationId = (int) ($message->organization_id ?? $payload['organization_id'] ?? 0);
        abort_if($organizationId <= 0, 422, 'Tenant realtime events require an organization.');
        if (class_exists('Redis') || class_exists('Predis\\Client')) {
            Redis::publish('tenant-events', json_encode([
                'organizationId' => $organizationId,
                'eventType' => $message->event_type,
                'version' => isset($payload['version']) ? (int) $payload['version'] : null,
                'payload' => $payload,
            ], JSON_THROW_ON_ERROR));
        }
        event(new TenantRealtimeEvent($organizationId, $message->event_type, $payload, isset($payload['version']) ? (int) $payload['version'] : null));
    }
}
