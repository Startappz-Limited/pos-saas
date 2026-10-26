<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Offline sync for the till (Phase 7, decision 4.5)
    |--------------------------------------------------------------------------
    |
    | lease_days: how far ahead a till may trade offline. Every sync hands the
    | till a server-signed "trade until" time: now plus these days, never past
    | the subscription's paid-up date. The till stops taking sales after it,
    | and the server accepts a pushed sale only inside a lease it issued.
    |
    */

    'lease_days' => (int) env('SYNC_LEASE_DAYS', 3),
];
