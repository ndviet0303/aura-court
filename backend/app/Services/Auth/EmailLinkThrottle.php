<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** BR-05/BR-10: verification and reset emails share per-account limits, keyed by the account's email. */
class EmailLinkThrottle
{
    public static function account(string $email): string
    {
        return Str::lower(trim($email));
    }

    /** Counts a send made outside the throttled routes (the registration email), using ThrottleRequests' hashed keys. */
    public static function hit(string $email): void
    {
        RateLimiter::hit(md5('auth-email-link'.'minute:'.self::account($email)), 60);
        RateLimiter::hit(md5('auth-email-link'.'hour:'.self::account($email)), 3600);
    }
}
