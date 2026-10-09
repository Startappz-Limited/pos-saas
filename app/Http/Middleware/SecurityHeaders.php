<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening headers on every response.
 *
 * Baseline from .ai/general/owasp-top-10-laravel-implementation-guide.md §1. Headers
 * already set by a response are left alone, so a controller can override one
 * deliberately (e.g. a page that must be framed).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach ($this->headers() as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        // HSTS only over a genuinely secure connection — sending it over plain HTTP
        // is meaningless, and sending it in local dev would pin developers to https.
        if ($request->isSecure() && app()->isProduction() && config('security.hsts.enabled')) {
            $response->headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            // Deliberately conservative rather than a full CSP: the admin theme uses
            // inline scripts/styles, so a script-src policy would need nonces
            // throughout the Blade layer. These directives are safe today and still
            // close off base-tag hijacking, plugin embedding and framing.
            'Content-Security-Policy' => "base-uri 'self'; object-src 'none'; frame-ancestors 'self'",
            'Permissions-Policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
        ];
    }

    private function hstsValue(): string
    {
        $value = 'max-age='.(int) config('security.hsts.max_age');

        if (config('security.hsts.include_subdomains')) {
            $value .= '; includeSubDomains';
        }

        if (config('security.hsts.preload')) {
            $value .= '; preload';
        }

        return $value;
    }
}
