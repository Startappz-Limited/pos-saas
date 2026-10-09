---
name: laravel-security
description: Mandatory security controls for this POS codebase — authentication, authorization, input validation with FormRequests, mass-assignment protection, XSS/CSRF/honeypot, SQL-injection prevention, encryption of PII and tokens, rate limiting, security headers, safe logging, webhook signature verification, and SSRF controls. Use when adding or reviewing any route, controller, form, API endpoint, webhook, file upload, login/auth flow, or anything touching credentials, tokens, or customer data.
---

# Security Standards (POS)

Sources: [1.0 security_best_practices_guide.md](../../../.ai/general/1.0%20security_best_practices_guide.md),
[owasp-top-10-laravel-implementation-guide.md](../../../.ai/general/owasp-top-10-laravel-implementation-guide.md),
[SECURITY_ENFORCEMENT.md](../../../.ai/general/SECURITY_ENFORCEMENT.md).

**Every security standard is 🔴 mandatory regardless of change type** — see the Security Standards
Enforcement table in [ENFORCEMENT_MATRIX.md](../../../.ai/general/ENFORCEMENT_MATRIX.md). Security is
the one area where "match the sibling code" does **not** excuse a gap: if the sibling is insecure, fix
the method you are touching and say so.

## Non-negotiables — check these on every change

| Control | Rule |
|---|---|
| Authentication | Every protected route/endpoint behind `auth` or `auth:sanctum`. No exceptions for "internal" routes. |
| Authorization | Every resource action authorized via policy or `permission:` middleware — see [laravel-authorization](../laravel-authorization/SKILL.md). |
| Validation | A FormRequest per store/update; controllers use `$request->validated()`. |
| Mass assignment | **Never** `$request->all()`. Every model declares `$fillable`. |
| CSRF | `@csrf` on every state-changing web form. |
| Bot protection | `@honeypot` on Blade forms (spatie/laravel-honeypot is installed and configured). |
| SQL | Eloquent / bindings only. Never interpolate input into raw SQL. |
| XSS | `{{ }}` escaping; no `{!! !!}` on user-supplied content. |
| Rate limiting | Done: `throttle:api` on the whole api group + named `auth`/`writes`/`destructive`/`webhooks` limiters (config/ratelimit.php). Add a tighter limiter for new money-affecting endpoints. |
| Secrets | Only in `.env`, read via `config()` — never `env()` outside `config/`. |
| Logging | Never log passwords, tokens, or PII. Sanitize before logging. |
| Encryption | PII, payment data, credentials, and tokens encrypted at rest. |
| Production | `APP_DEBUG=false`, `APP_ENV=production`, HTTPS enforced. |

## Input handling

```php
// ✅ FormRequest + validated()
public function store(StoreSaleRequest $request): RedirectResponse
{
    $sale = $this->saleService->create($request->validated());
    // ...
}
```

- One FormRequest per endpoint; put authorization in `authorize()` and normalization in
  `prepareForValidation()`.
- Bound numeric input to prevent overflow/abuse — the existing `StoreSaleRequest` is the model to
  copy: `'items.*.quantity' => ['required','integer','min:1','max:10000']`,
  `'items.*.price' => ['required','numeric','min:0','max:1000000']`.
- File uploads: validate `image`/`mimes`, `max:` size, and dimensions; verify the real MIME type
  (`$file->getMimeType()`) — never trust the extension.
- Whitelist any column name that comes from the request before using it in `orderBy`/`where`:

```php
$allowed = ['name', 'created_at', 'total_amount'];
$sort = in_array($request->sort, $allowed, true) ? $request->sort : 'created_at';
```

## Encryption & secrets

- Encrypt at rest with `Crypt` or an `encrypted` cast any field holding PII (name, email, phone,
  address), payment data, credentials, or API tokens. `social_accounts` tokens already do this —
  follow that pattern.
- Fields you must query or index cannot be plainly encrypted; use a blind-index/hash column alongside
  the encrypted value.
- Passwords: `Hash::make()` only. Apply `Password::defaults()` (min 8, mixed case, numbers, symbols,
  `uncompromised()`) for password rules.
- Queue payloads with sensitive data: implement `ShouldBeEncrypted` on the job.

## Multi-shop data isolation (project-specific, treat as security)

This is a multi-shop system: a missing shop filter is an authorization bug, not a UI glitch.

- Every shop-owned query filters by the acting user's shop. The established idiom is
  `auth()->user()->shop_id ?? Shop::first()?->id` — match the sibling code, and never widen a query
  that was previously shop-scoped.
- Policies must compare `$user->shop_id` to the record's `shop_id` (as `SalePolicy` does), not just
  check the permission name.
- Note `Gate::before` in `AppServiceProvider` grants `super-admin` everything — so a policy that
  only checks permissions still lets cross-shop access through for other roles unless it also checks
  `shop_id`.
- Cache keys, report filters, and export queries must all carry the shop scope.

## Webhooks, signed URLs, and outbound requests

- Inbound webhooks (`/api/webhooks/woocommerce/{shop:uuid}`, `/shopify/{shop:uuid}`, `/baileys`) are
  **not** session-authenticated — they must verify the HMAC signature before doing anything, and
  reject with 401/403 on mismatch. Log signature failures (without the payload secrets).
- Compare signatures with `hash_equals()` over the **raw body** (`$request->getContent()`), never `==`
  and never against re-encoded JSON.
- All three handlers **fail closed**: a missing secret returns 400, a bad signature 401, and nothing is
  queued either way. This is regression-tested in
  [tests/Feature/WebhookSignatureVerificationTest.php](../../../tests/Feature/WebhookSignatureVerificationTest.php)
  — never reintroduce a `if ($secret) { verify }` conditional, which is how the WooCommerce handler
  previously accepted unauthenticated payloads.
- Public document links (invoices, statements) use signed URLs (`->middleware('signed')`).
- Any feature that fetches a user-supplied URL needs SSRF controls: allow `https` only, block
  localhost/private/link-local/metadata ranges, validate the resolved IP, prefer allowlists.
- Never `unserialize()` untrusted input.

## Response & transport hardening

- Security headers on all responses (`X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy`, `Content-Security-Policy`, `Permissions-Policy`; HSTS only on secure production
  requests). `bootstrap/app.php` currently registers **no** global middleware — if you add the
  headers middleware, append it there via `->withMiddleware()`.
- Session cookies in production: `secure`, `http_only`, `same_site=lax`. Regenerate the session after
  login.
- Restrict CORS to known origins.

## Logging safely

```php
// ❌ may contain passwords/tokens/PII
Log::info('Login attempt', $request->all());

// ✅ explicit safe fields
Log::info('Login attempt', $request->only(['email']) + ['ip' => $request->ip()]);
```

Log security-relevant events (failed logins, permission changes, access denials, webhook signature
failures, rate-limit spikes) — with actor, IP, and user agent, and never with secrets.

## Auditing state changes

State-changing operations on user accounts, payments/credit records, and admin-privileged routes must
be audited — see [laravel-audit](../laravel-audit/SKILL.md).

## Verification before finishing

```bash
grep -rn '\$request->all()' app/Http/Controllers        # must be empty
grep -rn 'env(' app/ config/../app                      # env() only inside config/
grep -rln '<form' resources/views | xargs grep -Ln '@csrf'   # forms missing @csrf
```

Then run `vendor/bin/pint` and the relevant tests. For pending baseline gaps (honeypot coverage,
password rules, HTTPS enforcement, headers middleware) see the PENDING section of
[SECURITY_ENFORCEMENT.md](../../../.ai/general/SECURITY_ENFORCEMENT.md) — don't silently re-open a
closed item.

## Forbidden

- ❌ `$request->all()`, unvalidated input, missing `$fillable`
- ❌ Raw SQL with interpolated input
- ❌ `{!! !!}` on user content
- ❌ Unauthenticated or unauthorized resource routes
- ❌ Un-throttled login/signup or API endpoints
- ❌ `env()` outside `config/`; secrets in code or committed files
- ❌ Logging passwords/tokens/PII
- ❌ Session-auth assumptions on webhook endpoints
- ❌ Dropping or widening an existing shop-scope filter
