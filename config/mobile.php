<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flutter App Location
    |--------------------------------------------------------------------------
    |
    | Local checkout of the Flutter POS client that consumes this API. Used by
    | `php artisan mobile:contract-diff` to compare the app's endpoint registry
    | against the routes this API actually registers.
    |
    | Developer-machine convenience only — nothing at runtime reads this, and it
    | is expected to be unset in production.
    |
    */

    'app_path' => env('MOBILE_APP_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Endpoint Registry
    |--------------------------------------------------------------------------
    |
    | Path, relative to the Flutter project root, of the file holding every
    | endpoint the app can call. Single source of truth on the client side.
    |
    */

    'endpoint_registry' => 'lib/core/constants/api_endpoints.dart',

];
