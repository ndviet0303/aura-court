<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuraBookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $actionUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = (string) (config('app.frontend_url') ?: config('app.url') ?: 'https://court.ziet.dev');
        $publicDomain = (str_contains($frontendUrl, 'localhost') || str_contains($frontendUrl, '127.0.0.1'))
            ? 'https://court.ziet.dev'
            : rtrim($frontendUrl, '/');

        $actionFullUrl = null;
        if ($this->actionUrl) {
            if (str_starts_with($this->actionUrl, 'http://') || str_starts_with($this->actionUrl, 'https://')) {
                $actionFullUrl = $this->actionUrl;
            } else {
                $actionFullUrl = $publicDomain . '/' . ltrim($this->actionUrl, '/');
            }
        }

        $logoUrl = $publicDomain . '/aura/badge-logo.png';

        return (new MailMessage)
            ->subject($this->title . ' · Aura Court')
            ->view('emails.booking-notification', [
                'user' => $notifiable,
                'title' => $this->title,
                'bodyText' => $this->body,
                'actionUrl' => $actionFullUrl,
                'actionText' => 'Mở Aura Court',
                'event' => $this->event,
                'logoUrl' => $logoUrl,
                'frontendUrl' => $publicDomain,
            ]);
    }

    private function payload(): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
        ];
    }
}
