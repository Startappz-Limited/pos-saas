<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Baileys via Chatway Gateway
    |--------------------------------------------------------------------------
    |
    | Baileys (https://github.com/WhiskeySockets/Baileys) is an unofficial
    | WhatsApp Web client. It runs in Chatway Gateway, a separate service that
    | this app reaches through the startappz/wa-gateway-laravel package; its
    | URL, API key and webhook secret live in config/wa-gateway.php
    | (WA_GATEWAY_*). This integration is completely separate from the
    | official WhatsApp Cloud API integration in `config('services.whatsapp')`.
    |
    */

    'queue' => env('BAILEYS_QUEUE', 'baileys'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Status Audience
    |--------------------------------------------------------------------------
    |
    | A status is encrypted for each recipient, so only listed numbers can see
    | it. Posts go to the session's most recently active private chats, up to
    | this many -- keep it at or below the gateway's STATUS_MAX_RECIPIENTS.
    |
    */

    'status_max_recipients' => (int) env('BAILEYS_STATUS_MAX_RECIPIENTS', 256),

    /*
    |--------------------------------------------------------------------------
    | Auto Notifications
    |--------------------------------------------------------------------------
    |
    | When enabled, completed POS sales and converted ecommerce orders will
    | trigger a Baileys message to the customer (if a connected session
    | exists for the shop). This is independent of the official WhatsApp
    | template flow and only fires if the customer has a phone number.
    |
    */

    'notify_on_sale' => (bool) env('BAILEYS_NOTIFY_ON_SALE', true),
    'notify_on_ecommerce_order' => (bool) env('BAILEYS_NOTIFY_ON_ECOMMERCE_ORDER', true),

    /*
    |--------------------------------------------------------------------------
    | Inbound Media Storage
    |--------------------------------------------------------------------------
    |
    | Chatway Gateway never forwards status/channel media and sends no bytes
    | for files over 5 MB. These settings are the server-side backstop: they
    | bound what a connected client phone can write to our disk, no matter
    | what the gateway sends. `shop_quota_bytes` is the per-tenant ceiling — once
    | a shop is over it, messages still arrive but their media is dropped.
    |
    | Retention is enforced by `php artisan baileys:prune-media` (scheduled
    | daily in routes/console.php).
    |
    */

    'media' => [
        'store_inbound' => (bool) env('BAILEYS_STORE_INBOUND_MEDIA', true),
        'max_bytes' => (int) env('BAILEYS_MEDIA_MAX_BYTES', 5 * 1024 * 1024),
        'chat_types' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('BAILEYS_MEDIA_CHAT_TYPES', 'private')),
        ))),
        'retention_days' => (int) env('BAILEYS_MEDIA_RETENTION_DAYS', 30),
        'shop_quota_bytes' => (int) env('BAILEYS_MEDIA_SHOP_QUOTA_BYTES', 2 * 1024 * 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Country Code
    |--------------------------------------------------------------------------
    |
    | Used to normalise local phone numbers (e.g. "0712345678" or "712345678")
    | into international format before resolving to a WhatsApp JID. Numbers
    | that already include a country code are left untouched.
    |
    */

    'default_country_code' => env('BAILEYS_DEFAULT_COUNTRY_CODE', '254'),
];
