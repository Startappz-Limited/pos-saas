<?php

use App\Http\Middleware\EnsureBusinessIsOpen;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsLinkedToShop;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        // Staff without a shop see the "contact your administrator" page
        // (web) or a 403 (api). While a business is closing, only its owner
        // gets in (business.open). Applied to the authenticated route groups.
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'shop.linked' => EnsureUserIsLinkedToShop::class,
            'business.open' => EnsureBusinessIsOpen::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An expired page (419: the form's CSRF token no longer matches the
        // session) is usually a session that changed after the page loaded:
        // signing in as someone else in another tab of the same browser,
        // signing out, or a session older than SESSION_LIFETIME. Send people
        // back with an explanation and what they typed, instead of an error
        // page. The API keeps its 419 JSON.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            return redirect()
                ->back(fallback: route('login'))
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password']))
                ->with('error', __('This page had expired: you signed in or out, or were away too long, since it was opened. It has been reloaded; please try again.'));
        });
    })->create();
