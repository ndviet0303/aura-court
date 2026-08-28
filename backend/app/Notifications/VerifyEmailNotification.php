<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        $frontendUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $logoUrl = $frontendUrl . '/aura/badge-logo.png';

        return (new MailMessage)
            ->subject('Xác thực địa chỉ Email · Aura Court')
            ->view('emails.verify-email', [
                'user' => null, // will be passed or extracted
                'url' => $url,
                'logoUrl' => $logoUrl,
                'frontendUrl' => $frontendUrl,
            ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $verificationUrl);
        }

        $frontendUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
        $logoUrl = $frontendUrl . '/aura/badge-logo.png';

        return (new MailMessage)
            ->subject('Xác thực địa chỉ Email · Aura Court')
            ->view('emails.verify-email', [
                'user' => $notifiable,
                'url' => $verificationUrl,
                'logoUrl' => $logoUrl,
                'frontendUrl' => $frontendUrl,
            ]);
    }
}
