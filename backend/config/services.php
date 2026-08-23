<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL', 'http://localhost:8000') . '/api/v1/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', env('APP_URL', 'http://localhost:8000') . '/api/v1/auth/facebook/callback'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'sepay' => [
        'bank' => env('SEPAY_BANK', 'VCB'),
        'account' => env('SEPAY_ACCOUNT'),
        'account_name' => env('SEPAY_ACCOUNT_NAME', 'AURA COURT'),
        'api_key' => env('SEPAY_API_KEY'),
        'hmac_secret' => env('SEPAY_HMAC_SECRET'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'payments' => [
        'default_provider' => env('PAYMENT_PROVIDER', 'sepay'),
    ],

    'payos' => [
        'base_url' => env('PAYOS_BASE_URL', 'https://api-merchant.payos.vn'),
        'client_id' => env('PAYOS_CLIENT_ID'),
        'api_key' => env('PAYOS_API_KEY'),
        'webhook_secret' => env('PAYOS_WEBHOOK_SECRET'),
        'timeout' => env('PAYOS_TIMEOUT', 10),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
