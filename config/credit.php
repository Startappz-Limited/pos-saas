<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Defaults for auto-created credit accounts
    |--------------------------------------------------------------------------
    |
    | A credit sale rung up for a wholesale customer who has no credit account
    | yet auto-creates one so the debt always lands in the ledger. These are the
    | fallback terms for that account. Each value may be overridden per shop via
    | `Shop.settings['credit']`.
    |
    */

    'default_limit' => (float) env('CREDIT_DEFAULT_LIMIT', 0),

    'default_payment_terms_days' => (int) env('CREDIT_DEFAULT_PAYMENT_TERMS_DAYS', 30),

    'default_grace_period_days' => (int) env('CREDIT_DEFAULT_GRACE_PERIOD_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Credit limit enforcement at the point of sale
    |--------------------------------------------------------------------------
    |
    | When false (the default) a credit sale that pushes a customer past their
    | limit is still recorded — the ledger must never be less complete than the
    | sales table — and the over-limit condition is reported back to the caller
    | as a warning. Set true to reject such sales outright.
    |
    */

    'enforce_limit_at_pos' => (bool) env('CREDIT_ENFORCE_LIMIT_AT_POS', false),

];
