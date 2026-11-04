<?php

/*
|--------------------------------------------------------------------------
| Gym platform defaults
|--------------------------------------------------------------------------
|
| Platform-wide defaults. Per-organization and per-branch values will live
| in the `settings` table (see ARCHITECTURE.md §3A) and override these.
|
*/

return [

    'locales' => ['en', 'am'],

    'timezone' => env('GYM_TIMEZONE', 'Africa/Addis_Ababa'),

    'currency' => [
        'code' => env('GYM_CURRENCY', 'ETB'),
        // Money is stored as integer minor units (santim): 1 ETB = 100 santim.
        'minor_units' => 100,
    ],

    'tax' => [
        'enabled' => (bool) env('GYM_TAX_ENABLED', false),
        'name' => env('GYM_TAX_NAME', 'VAT'),
        'rate' => (float) env('GYM_TAX_RATE', 15.00),
        'prices_inclusive' => (bool) env('GYM_TAX_PRICES_INCLUSIVE', true),
    ],

    'features' => [
        'freeze' => (bool) env('GYM_FEATURE_FREEZE', false),
        'lockers' => (bool) env('GYM_FEATURE_LOCKERS', false),
        'chapa' => (bool) env('GYM_FEATURE_CHAPA', false),
    ],

    'payments' => [
        'chapa' => [
            'base_url' => env('CHAPA_BASE_URL', 'https://api.chapa.co/v1'),
            'secret_key' => env('CHAPA_SECRET_KEY'),
            'public_key' => env('CHAPA_PUBLIC_KEY'),
            'webhook_secret' => env('CHAPA_WEBHOOK_SECRET'),
        ],
    ],

    'notifications' => [
        // Channels a member can pick as their preferred notification channel.
        'channels' => ['telegram', 'whatsapp', 'sms', 'email'],
        'default_channel' => env('NOTIFY_DEFAULT_CHANNEL', 'sms'),

        'telegram' => [
            'enabled' => (bool) env('TELEGRAM_ENABLED', false),
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'bot_username' => env('TELEGRAM_BOT_USERNAME'),
            'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        ],

        'whatsapp' => [
            'enabled' => (bool) env('WHATSAPP_ENABLED', false),
            'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v21.0'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        ],

        'sms' => [
            'enabled' => (bool) env('SMS_ENABLED', false),
            'driver' => env('SMS_DRIVER', 'log'),
            'api_key' => env('SMS_API_KEY'),
            'sender_id' => env('SMS_SENDER_ID'),
        ],

        // Delivery itself uses Laravel's mail config (MAIL_* variables).
        'email' => [
            'enabled' => (bool) env('EMAIL_NOTIFICATIONS_ENABLED', true),
        ],
    ],

];
