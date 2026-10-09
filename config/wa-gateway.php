<?php

return [

    /*
    | Where the gateway lives and this system's API key for it. Keys are minted
    | per system and per environment by the gateway operator: wag_live_... for
    | production, wag_test_... for everything else.
    */

    'url' => env('WA_GATEWAY_URL'),

    'token' => env('WA_GATEWAY_TOKEN'),

    /*
    | A send can wait up to 15 seconds on the gateway for a reconnecting session
    | before it answers, so keep the timeout above that.
    */

    'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 30),

    'connect_timeout' => (int) env('WA_GATEWAY_CONNECT_TIMEOUT', 5),

    /*
    | Retries after a connection failure or a 5xx. Sends always carry an
    | Idempotency-Key, so a retry never sends twice.
    */

    'retries' => (int) env('WA_GATEWAY_RETRIES', 2),

    /*
    | Lets numbers written locally ("0712 345 678") be addressed: a leading 0 is
    | replaced with this country code. Leave empty to accept international
    | numbers only.
    */

    'default_country_code' => env('WA_GATEWAY_DEFAULT_COUNTRY_CODE'),

    'webhook' => [

        // The tenant's webhook secret, from the gateway operator.
        'secret' => env('WA_GATEWAY_WEBHOOK_SECRET'),

        // Where the gateway POSTs events. Set to null to register the route yourself.
        'path' => env('WA_GATEWAY_WEBHOOK_PATH', 'wa-gateway/webhook'),

        // Extra middleware for the webhook route. It never gets the "web" group,
        // so there is no CSRF check or session on it. The limiter is defined in
        // AppServiceProvider; see config/ratelimit.php for why it is its own.
        'middleware' => ['throttle:wa-gateway-webhook'],

        // Requests signed further than this from the app's clock are refused:
        // that window is what makes a captured request useless to replay.
        'tolerance' => (int) env('WA_GATEWAY_WEBHOOK_TOLERANCE', 300),

        // Deliveries already handled are remembered here, so a redelivery is
        // acknowledged without firing its events again. With more than one app
        // server this must be a shared store (redis, database).
        'dedupe_store' => env('WA_GATEWAY_WEBHOOK_DEDUPE_STORE'),

        // Longer than the gateway retries a delivery (72 hours).
        'dedupe_ttl' => (int) env('WA_GATEWAY_WEBHOOK_DEDUPE_TTL', 4 * 24 * 60 * 60),
    ],

];
