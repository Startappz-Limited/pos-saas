<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Strict Tenant Context
    |--------------------------------------------------------------------------
    |
    | When true, querying tenant data with no business set throws
    | MissingTenantContext instead of running unscoped. On in local and
    | testing, so code that forgets to set the business fails loudly. In
    | production a signed-in user without a business always gets no rows; only
    | code with no signed-in user (jobs, commands) runs unscoped, with a
    | warning, until stage 1c makes every job and command set its business.
    |
    */

    'strict' => (bool) env('TENANCY_STRICT', in_array(env('APP_ENV'), ['local', 'testing'], true)),

];
