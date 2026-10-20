<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lapse and grace (decisions 4.6, 4.9, assumption 12)
    |--------------------------------------------------------------------------
    |
    | A lapsed subscription is read-only plus export for this many business
    | days (Monday to Friday, excluding the holidays below); on the next the
    | closure flow starts. The owner is warned this many business days before.
    |
    */

    'grace_business_days' => (int) env('BILLING_GRACE_BUSINESS_DAYS', 30),

    'warn_business_days_before' => (int) env('BILLING_WARN_BUSINESS_DAYS', 5),

    /*
    |--------------------------------------------------------------------------
    | Public holidays (assumption 12)
    |--------------------------------------------------------------------------
    |
    | Kenyan public holidays that are not business days, as Y-m-d. Edit as the
    | government gazettes them; holidays falling on a weekend change nothing.
    |
    */

    'holidays' => [
        '2026-10-20', // Mashujaa Day
        '2026-12-12', // Jamhuri Day
        '2026-12-25', // Christmas Day
        '2026-12-26', // Boxing Day
        '2027-01-01', // New Year's Day
        '2027-03-26', // Good Friday
        '2027-03-29', // Easter Monday
        '2027-05-01', // Labour Day
        '2027-06-01', // Madaraka Day
        '2027-10-20', // Mashujaa Day
        '2027-12-13', // Jamhuri Day (12 December is a Sunday)
        '2027-12-27', // Christmas observed
    ],

    /*
    |--------------------------------------------------------------------------
    | Writes allowed while a business is read-only (decisions 4.6, 4.9)
    |--------------------------------------------------------------------------
    |
    | Route-name or path patterns (Str::is). Signing in and out, switching
    | business, exporting, paying, closing the business and deleting shops
    | keep working; everything else that writes is refused with
    | `subscription_inactive`.
    |
    */

    'read_only_allowed' => [
        'logout', 'api/logout', 'login',
        'password.*', 'verification.*', 'profile.*',
        'businesses.*',
        'business.exports.*', 'business.closure.*', 'business.handover.*', 'business.data',
        'shops.destroy', 'api.shops.destroy',
        'billing.*', 'webhooks.*',
        'api.v1.auth.sign-out', 'api.v1.businesses.switch',
        'api.v1.business.exports', 'api.v1.business.closure*',
        'api.v1.billing.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Collection (decision 4.6)
    |--------------------------------------------------------------------------
    |
    | Until the platform goes live on M-Pesa (about 12 November 2026) payments
    | are recorded by hand. With this switch on, the PaymentCollector adapter
    | over the private M-Pesa package starts STK pushes and confirms payments by
    | querying the API.
    |
    */

    'mpesa_enabled' => (bool) env('BILLING_MPESA_ENABLED', false),

    'currency' => 'KES',

    /*
    |--------------------------------------------------------------------------
    | AI top-ups
    |--------------------------------------------------------------------------
    */

    'ai_min_topup' => (int) env('BILLING_AI_MIN_TOPUP', 500),

];
