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

];
