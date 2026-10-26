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

    /*
    | Till PIN lockout (decided 26 October 2026), applied by the till offline
    | and sent to it with the settings: after `pin_max_attempts` wrong PINs a
    | person waits `pin_lock_minutes`, doubled for each further lockout that
    | day; after `pin_lockouts_before_manager` lockouts in a day only someone
    | holding `pin_unlock_permission` can unlock them, and the server keeps
    | that lock so it reaches the business's other tills too.
    */
    'pin_max_attempts' => (int) env('SYNC_PIN_MAX_ATTEMPTS', 4),
    'pin_lock_minutes' => (int) env('SYNC_PIN_LOCK_MINUTES', 15),
    'pin_lockouts_before_manager' => (int) env('SYNC_PIN_LOCKOUTS_BEFORE_MANAGER', 3),
    'pin_unlock_permission' => 'users.update',

    // Items a till may push at once.
    'push_max_items' => (int) env('SYNC_PUSH_MAX_ITEMS', 100),
];
