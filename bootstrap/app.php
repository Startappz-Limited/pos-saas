<?php

use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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

        // The business a signed-in request acts for (stage 1a, decision 4.3).
        // It needs the authenticated user, so it's ordered after
        // authentication wherever both apply.
        $middleware->web(append: [ResolveTenant::class]);
        $middleware->api(append: [ResolveTenant::class]);
        $middleware->appendToPriorityList(AuthenticatesRequests::class, ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
