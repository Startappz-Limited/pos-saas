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

    /*
    |--------------------------------------------------------------------------
    | Signup (Phase 6)
    |--------------------------------------------------------------------------
    |
    | `check_dns` asks DNS whether an email's domain accepts mail; `breach_check`
    | asks Have I Been Pwned (range API, k-anonymity) whether a password was
    | leaked. Both are network calls: off in tests, and both fail open on error.
    |
    */

    'signup' => [
        'check_dns' => (bool) env('SECURITY_SIGNUP_CHECK_DNS', true),
        'breach_check' => (bool) env('SECURITY_SIGNUP_BREACH_CHECK', true),
        'attempts_per_window' => 5,
        'window_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email verification grace (Phase 6)
    |--------------------------------------------------------------------------
    |
    | An owner may work unverified for 24 hours after signup (or an email
    | change). After that everything is refused with `email_verification_required`
    | except these route names and paths: verifying, resending the link,
    | changing the email, and signing out.
    |
    */

    'verification_allowed' => [
        'verification.*', 'logout', 'api/logout', 'api.v1.auth.sign-out',
    ],

];
