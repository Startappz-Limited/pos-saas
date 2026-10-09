<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Rate Limits
    |--------------------------------------------------------------------------
    |
    | Requests allowed per minute for each named limiter, registered in
    | AppServiceProvider::boot(). Rate limiting is a mandatory standard
    | (.ai/general/1.0 security_best_practices_guide.md) — these values are
    | deliberately generous enough for the Flutter client's normal traffic while
    | still bounding abuse.
    |
    | Tune via env rather than editing code, so a shop with unusual volume can be
    | accommodated without a deploy.
    |
    */

    // General authenticated API traffic, keyed by user (falling back to IP).
    'api' => (int) env('RATE_LIMIT_API', 120),

    // Login / registration. Keyed by IP *and* submitted email so one attacker
    // cannot lock out a legitimate user by hammering their address.
    'auth' => (int) env('RATE_LIMIT_AUTH', 5),

    // Inbound platform webhooks, keyed per shop. WooCommerce and Shopify burst
    // during bulk syncs, so this is high — it exists to bound abuse, not to
    // shape normal traffic.
    'webhooks' => (int) env('RATE_LIMIT_WEBHOOKS', 300),

    // Chatway Gateway's WhatsApp events for every shop arrive from one IP, and
    // a busy number or a history sync bursts far past the platform webhooks.
    // Point capped the same firehose at 120/min and 429'd its inbox into
    // silence, so this is its own high ceiling. The gateway retries a 429, so
    // hitting it delays events rather than losing them.
    'wa_gateway_webhooks' => (int) env('RATE_LIMIT_WA_GATEWAY_WEBHOOKS', 1200),

    // Creating money-affecting records (sales, payments).
    'writes' => (int) env('RATE_LIMIT_WRITES', 30),

    // Destructive or irreversible actions (void, refund, register close).
    'destructive' => (int) env('RATE_LIMIT_DESTRUCTIVE', 10),

];
