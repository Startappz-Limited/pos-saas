<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Strict Tenant Context
    |--------------------------------------------------------------------------
    |
    | When true, querying tenant data with no business set throws
    | MissingTenantContext, even for a signed-in user. On in local and
    | testing, so code that forgets to set the business fails loudly. In
    | production a signed-in user without a business gets no rows, and code
    | with no signed-in user (jobs, commands, the scheduler) is refused as in
    | strict mode: since stage 1c each of them acts for a named business.
    |
    */

    'strict' => (bool) env('TENANCY_STRICT', in_array(env('APP_ENV'), ['local', 'testing'], true)),

    /*
    |--------------------------------------------------------------------------
    | File storage quota
    |--------------------------------------------------------------------------
    |
    | Bytes each business may store across its files (stage 1c): product
    | images, profile photos, WhatsApp media. A business can be given its own
    | limit in businesses.settings['files_quota_bytes']; plans set it in
    | Phase 4. Zero or less means no limit.
    |
    */

    'files_quota_bytes' => (int) env('TENANT_FILES_QUOTA_BYTES', 2 * 1024 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Closure grace period
    |--------------------------------------------------------------------------
    |
    | Days between a business's closure and the erasure of its data (stage 3).
    | Its owners can download the final export and cancel the closure until
    | then.
    |
    */

    'closure_grace_days' => (int) env('BUSINESS_CLOSURE_GRACE_DAYS', 30),

];
