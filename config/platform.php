<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform console (Phase 5)
    |--------------------------------------------------------------------------
    |
    | impersonation_minutes: how long a platform operator may act as a business
    | user before the session ends by itself and the operator is signed back in.
    |
    */

    'impersonation_minutes' => (int) env('PLATFORM_IMPERSONATION_MINUTES', 30),

];
