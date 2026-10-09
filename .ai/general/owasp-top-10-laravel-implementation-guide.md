# OWASP Top 10 Laravel Implementation Guide

This guide turns the OWASP Top 10 checklist into a practical Laravel baseline that can be copied into other projects. Treat it as a starting point, then tighten each control to match the project's authentication model, data sensitivity, infrastructure, and threat model.

## 1. Baseline Security Headers

Create a global middleware that adds browser hardening headers to every response.

Recommended headers:

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: no-referrer`
- `Content-Security-Policy: base-uri 'self'; object-src 'none'; frame-ancestors 'none'`
- `Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` for secure production requests only

Example middleware:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        if ($request->isSecure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function headers(): array
    {
        return [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => "base-uri 'self'; object-src 'none'; frame-ancestors 'none'",
            'Permissions-Policy' => 'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()',
        ];
    }
}
```

Register it globally in Laravel 11 or newer:

```php
use App\Http\Middleware\SecurityHeaders;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->append(SecurityHeaders::class);
})
```

## 2. Secure Environment Defaults

Production `.env` values should be explicit:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

SECURITY_HEADERS_ENABLED=true
SECURITY_HSTS_ENABLED=true
SECURITY_HSTS_MAX_AGE=31536000
SECURITY_HSTS_INCLUDE_SUBDOMAINS=true
SECURITY_HSTS_PRELOAD=false
```

Use `config()` in application code. Keep `env()` calls inside configuration files only.

## 3. Broken Access Control

Use server-side authorization for every protected action.

- Protect private web routes with `auth` middleware.
- Protect APIs with Sanctum or the project's token guard.
- Use policies for resource ownership and permission checks.
- Use UUID route keys for public resource references when possible.
- Deny by default when a user, organisation, membership, or permission cannot be resolved.

Example policy check:

```php
public function update(User $user, Document $document): bool
{
    return $document->organisation
        ->members()
        ->whereKey($user->id)
        ->exists();
}
```

## 4. Cryptographic Failures

- Hash passwords with Laravel's `Hash` facade.
- Use HTTPS in production and emit HSTS only over secure requests.
- Encrypt sensitive model attributes with Laravel encrypted casts when the database should not hold plaintext.
- Never log tokens, passwords, OAuth secrets, reset links, or private document URLs.

Example encrypted cast:

```php
protected function casts(): array
{
    return [
        'api_config' => 'encrypted:array',
    ];
}
```

## 5. Injection Prevention

- Prefer Eloquent relationships and query builder bindings.
- Never concatenate user input into SQL.
- Validate request input with Form Request classes.
- Use `$request->validated()` instead of `$request->all()`.
- Escape output in Blade with `{{ }}` unless trusted HTML is intentionally sanitized first.

## 6. Insecure Design

- Rate limit login, registration, password reset, verification, booking, upload, webhook, and public write endpoints.
- Add abuse limits to expensive jobs and external API calls.
- Require confirmation for destructive actions.
- Document trust boundaries for webhooks, OAuth callbacks, public signed URLs, and internal service routes.

Example route throttle:

```php
Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1');
```

## 7. Security Misconfiguration

- Keep debug mode disabled in production.
- Configure trusted proxies and hosts at the web server or Laravel middleware layer.
- Restrict CORS to known origins.
- Disable unused services and default credentials.
- Keep `robots.txt` from exposing private paths as indexable content.

## 8. Vulnerable and Outdated Components

Run dependency checks in CI and before releases:

```bash
composer audit
npm audit
```

Patch high and critical advisories quickly. Remove packages that are no longer used.

## 9. Identification and Authentication Failures

- Regenerate sessions after login.
- Enforce strong passwords through Laravel password rules or a project password policy.
- Add MFA or OTP for sensitive applications.
- Use secure, HTTP-only, same-site cookies.
- Lock or throttle repeated failed login attempts.

Example session regeneration:

```php
$request->session()->regenerate();
```

## 10. Software and Data Integrity Failures

- Avoid `unserialize()` on untrusted input.
- Protect CI secrets and deployment tokens.
- Pin and audit build dependencies.
- Validate webhook signatures before accepting event payloads.
- Keep queue payloads free of sensitive plaintext, or encrypt jobs when payloads include sensitive data.

## 11. Logging and Monitoring

Log security-relevant events without leaking secrets:

- Failed logins and lockouts
- Password resets and MFA changes
- Role and permission changes
- Access denials for sensitive resources
- Webhook signature failures
- Rate-limit spikes

Send logs to a monitored system in production and define alert thresholds for repeated abuse.

## 12. SSRF Prevention

For any feature that fetches user-supplied URLs:

- Validate the URL scheme and host.
- Allow only `https` unless there is a clear reason.
- Block localhost, private networks, link-local ranges, and metadata service IPs.
- Resolve DNS and validate the resolved IP before making the request.
- Prefer allowlists for integrations with known providers.

## 13. Tests to Add

Every project should include focused tests for the security baseline:

- Security headers are present on normal web responses.
- HSTS is present only for secure production requests.
- Login or OTP endpoints are throttled.
- Private routes redirect guests or reject unauthenticated API requests.
- Users cannot access another user's or organisation's resources.
- Signed URLs reject missing, expired, or tampered signatures.
- Webhooks reject invalid signatures.

Example Pest assertion:

```php
it('adds baseline security headers', function () {
    $this->get('/')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});
```

## 14. Rollout Checklist

- [ ] Add global security headers middleware.
- [ ] Configure production session cookie flags.
- [ ] Verify auth routes are throttled.
- [ ] Review policies for protected resources.
- [ ] Replace inline validation with Form Request classes where needed.
- [ ] Remove direct `env()` calls outside config files.
- [ ] Add dependency audits to CI.
- [ ] Add security event logging.
- [ ] Review outbound URL fetches for SSRF controls.
- [ ] Run focused security tests before release.
