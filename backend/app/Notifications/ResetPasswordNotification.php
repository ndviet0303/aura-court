<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $frontendUrl = (string) (config('app.frontend_url') ?: config('app.url') ?: 'https://court.ziet.dev');
        $publicDomain = (str_contains($frontendUrl, 'localhost') || str_contains($frontendUrl, '127.0.0.1'))
            ? 'https://court.ziet.dev'
            : rtrim($frontendUrl, '/');
        $logoUrl = $publicDomain.'/aura/badge-logo.png';

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu · Aura Court')
            ->view('emails.reset-password', [
                'user' => $notifiable,
                'url' => $url,
                'expires' => config('auth.passwords.users.expire', 60),
                'logoUrl' => $logoUrl,
                'frontendUrl' => $publicDomain,
            ]);
    }
}
