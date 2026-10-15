<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class VerifyEmailNotification extends BaseVerifyEmail
{
    /**
     * BR-05: the link carries a one-time token instead of sha1(email). Issuing a link replaces
     * the previous token, so only the newest link works, and it works once.
     */
    protected function verificationUrl($notifiable): string
    {
        $expires = now()->addMinutes((int) config('auth.verification.expire', 15));
        $token = Str::random(40);
        Cache::put(self::tokenKey($notifiable), $token, $expires);

        return URL::temporarySignedRoute('verification.verify', $expires, [
            'id' => $notifiable->getKey(),
            'hash' => $token,
        ]);
    }

    public static function matches(object $notifiable, string $token): bool
    {
        $stored = Cache::get(self::tokenKey($notifiable));

        return is_string($stored) && hash_equals($stored, $token);
    }

    public static function forget(object $notifiable): void
    {
        Cache::forget(self::tokenKey($notifiable));
    }

    private static function tokenKey(object $notifiable): string
    {
        return 'email-verification:'.$notifiable->getKey();
    }

    protected function buildMailMessage($url): MailMessage
    {
        $frontendUrl = (string) (config('app.frontend_url') ?: config('app.url') ?: 'https://court.ziet.dev');
        $publicDomain = (str_contains($frontendUrl, 'localhost') || str_contains($frontendUrl, '127.0.0.1'))
            ? 'https://court.ziet.dev'
            : rtrim($frontendUrl, '/');
        $logoUrl = $publicDomain.'/aura/badge-logo.png';

        return (new MailMessage)
            ->subject('Xác thực địa chỉ Email · Aura Court')
            ->view('emails.verify-email', [
                'user' => null, // will be passed or extracted
                'url' => $url,
                'logoUrl' => $logoUrl,
                'frontendUrl' => $publicDomain,
            ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        if (static::$toMailCallback) {
            return call_user_func(static::$toMailCallback, $notifiable, $verificationUrl);
        }

        $frontendUrl = (string) (config('app.frontend_url') ?: config('app.url') ?: 'https://court.ziet.dev');
        $publicDomain = (str_contains($frontendUrl, 'localhost') || str_contains($frontendUrl, '127.0.0.1'))
            ? 'https://court.ziet.dev'
            : rtrim($frontendUrl, '/');
        $logoUrl = $publicDomain.'/aura/badge-logo.png';

        return (new MailMessage)
            ->subject('Xác thực địa chỉ Email · Aura Court')
            ->view('emails.verify-email', [
                'user' => $notifiable,
                'url' => $verificationUrl,
                'logoUrl' => $logoUrl,
                'frontendUrl' => $publicDomain,
            ]);
    }
}
