<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Browser hardening headers on every response (web and api).
        $middleware->append(SecurityHeaders::class);

        // Baseline throttle on every API route, so a new endpoint is rate limited
        // by default rather than by remembering to add it. Tighter named limiters
        // (auth, writes, destructive, webhooks) are applied per route in
        // routes/api.php. Limits live in config/ratelimit.php.
        $middleware->api(append: [
            'throttle:api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
