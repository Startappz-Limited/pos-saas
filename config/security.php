<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HTTPS Enforcement
    |--------------------------------------------------------------------------
    |
    | When enabled, generated URLs are forced to https. Defaults to on in
    | production only, so local Herd/serve traffic over http keeps working.
    |
    */

    'force_https' => (bool) env('SECURITY_FORCE_HTTPS', env('APP_ENV') === 'production'),

    /*
    |--------------------------------------------------------------------------
    | HSTS
    |--------------------------------------------------------------------------
    |
    | Only emitted on secure production requests (see SecurityHeaders middleware).
    | Enable `preload` only once you are certain every subdomain is https-only —
    | it is difficult to reverse.
    |
    */

    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS_ENABLED', true),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | Applied via Password::defaults() in AppServiceProvider. `uncompromised`
    | checks the password against the Have I Been Pwned breach corpus over the
    | network, so it is disabled in tests to keep them offline and fast.
    |
    */

    'passwords' => [
        'min_length' => (int) env('SECURITY_PASSWORD_MIN_LENGTH', 8),
        'mixed_case' => (bool) env('SECURITY_PASSWORD_MIXED_CASE', true),
        'numbers' => (bool) env('SECURITY_PASSWORD_NUMBERS', true),
        'symbols' => (bool) env('SECURITY_PASSWORD_SYMBOLS', true),
        'uncompromised' => (bool) env('SECURITY_PASSWORD_UNCOMPROMISED', true),
    ],

];
