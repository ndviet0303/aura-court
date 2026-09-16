<?php

namespace App\Services\Infrastructure;

use App\Services\Payment\ProviderOutboxHandler;

use App\Events\TenantRealtimeEvent;
use App\Models\OutboxMessage;
use App\Models\User;
use App\Notifications\OutboxNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
class OutboxDispatcher
{
    public function dispatch(OutboxMessage $message): void
    {
        $payload = $message->payload ?? [];
        match ($message->channel) {
            'notification' => $this->notification($message, $payload),
            'realtime' => $this->realtime($message, $payload),
            'provider' => app(ProviderOutboxHandler::class)->handle($message),
            default => throw new \InvalidArgumentException("Unsupported outbox channel [{$message->channel}]."),
        };
    }

    private function notification(OutboxMessage $message, array $payload): void
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $user = User::findOrFail($userId);

        $channels = $payload['channels'] ?? ['database'];
        $delivered = $payload['delivered_channels'] ?? [];

        // 1. Deliver to database if requested and not yet delivered
        if (in_array('database', $channels, true) && ! in_array('database', $delivered, true)) {
            $notificationData = $payload['notification'] ?? [
                'event' => $payload['event'] ?? $message->event_type,
                'title' => $payload['title'] ?? 'Thông báo',
                'body' => $payload['body'] ?? '',
                'action_url' => $payload['action_url'] ?? null,
                'booking_id' => $payload['booking_id'] ?? null,
                'booking_code' => $payload['booking_code'] ?? null,
            ];
            $user->notify(new OutboxNotification($notificationData));
            $delivered[] = 'database';
            $payload['delivered_channels'] = $delivered;
            $message->update(['payload' => $payload]);
        }

        // 2. Deliver to mail if requested and not yet delivered
        if (in_array('mail', $channels, true) && ! in_array('mail', $delivered, true)) {
            $this->sendMailNotification($user, $message, $payload);
            $delivered[] = 'mail';
            $payload['delivered_channels'] = $delivered;
            $message->update(['payload' => $payload]);
        }
    }

    private function sendMailNotification(User $user, OutboxMessage $message, array $payload): void
    {
        $frontendUrl = (string) (config('app.frontend_url') ?: config('app.url') ?: 'https://court.ziet.dev');
        $publicDomain = (str_contains($frontendUrl, 'localhost') || str_contains($frontendUrl, '127.0.0.1'))
            ? 'https://court.ziet.dev'
            : rtrim($frontendUrl, '/');

        $actionFullUrl = null;
        $actionUrl = $payload['action_url'] ?? null;
        if ($actionUrl) {
            if (str_starts_with($actionUrl, 'http://') || str_starts_with($actionUrl, 'https://')) {
                $actionFullUrl = $actionUrl;
            } else {
                $actionFullUrl = $publicDomain . '/' . ltrim($actionUrl, '/');
            }
        }

        $logoUrl = $publicDomain . '/aura/badge-logo.png';
        $mailView = $payload['mail_view'] ?? 'emails.booking-notification';
        $title = (string) ($payload['title'] ?? 'Thông báo Aura Court');
        $body = (string) ($payload['body'] ?? '');
        $event = (string) ($payload['event'] ?? $message->event_type);

        Mail::send($mailView, [
            'user' => $user,
            'title' => $title,
            'bodyText' => $body,
            'actionUrl' => $actionFullUrl,
            'actionText' => $payload['action_text'] ?? 'Mở Aura Court',
            'event' => $event,
            'logoUrl' => $logoUrl,
            'frontendUrl' => $publicDomain,
        ], function ($mail) use ($user, $title) {
            $mail->to($user->email, $user->name)
                ->subject($title . ' · Aura Court');
        });
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
