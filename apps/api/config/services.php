<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
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

    // Web Push (VAPID) for the app's notifications: `php artisan notifications:vapid-keys` makes a pair.
    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:support@muhasebi.com'),
    ],

    // Google Play app (Trusted Web Activity): its package and signing key fingerprint(s), comma separated.
    'twa' => [
        'package' => env('TWA_PACKAGE', ''),
        'fingerprints' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('TWA_SHA256', ''))))),
    ],

    // Passkeys (unlocking the app with a fingerprint / face). rp_id = the app's host; origins = the
    // pages allowed to use them (comma separated, default APP_URL).
    'webauthn' => [
        'rp_id' => env('WEBAUTHN_RP_ID', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        'origins' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('WEBAUTHN_ORIGINS', env('APP_URL', 'http://localhost')))))),
    ],

];
