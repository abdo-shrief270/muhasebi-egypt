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
    // Alerts to the platform team (server errors, health): a Telegram bot and the chat it writes to.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => env('TELEGRAM_CHAT_ID', ''),
    ],

    // /api/v1/health: below this much free disk it fails.
    'monitoring' => [
        'min_free_percent' => (int) env('MONITOR_MIN_FREE_PERCENT', 10),
    ],

    'webpush' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:support@muhasebi.com'),
    ],

    // The online stores: each at a subdomain of `host` ({slug}.muhasebi.com), or — without a host —
    // at {url}/{slug}. `url` may hold the shop's place as {slug} (https://{slug}.muhasebi.com).
    'store' => [
        'host' => env('STORE_HOST', ''),
        'url' => env('STORE_URL', 'https://store.muhasebi.com'),
    ],

    // «سوق محاسبي»: the public site's address once it's launched (empty = «قريباً» in the app).
    'market' => [
        'url' => rtrim((string) env('MARKET_URL', ''), '/'),
    ],

    // Elasticsearch for «سوق محاسبي» (the marketplace search). Empty url = search off. Prefer an API key
    // (base64 "id:key") over a username / password; `ca` = path to the cluster's CA certificate when it is
    // self-signed (else the system CAs). Indices are named {prefix}market_offers_v{n} behind an alias.
    'search' => [
        'url' => rtrim((string) env('SEARCH_URL', ''), '/'),
        'api_key' => env('SEARCH_API_KEY', ''),
        'username' => env('SEARCH_USERNAME', ''),
        'password' => env('SEARCH_PASSWORD', ''),
        'ca' => env('SEARCH_CA', ''),
        'prefix' => env('SEARCH_PREFIX', 'muhasebi_'),
        'timeout' => (int) env('SEARCH_TIMEOUT', 5),
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
