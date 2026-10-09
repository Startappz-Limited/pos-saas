---
name: laravel-integrations
description: Standards for this POS codebase's external integrations — WooCommerce and Shopify two-way sync, inbound webhooks and HMAC verification, the Baileys WhatsApp Node bridge and Meta Cloud API, Meta/Google Ads, social posting drivers, and the multi-provider AiManager. Covers credential encryption, per-shop config, queued jobs with retries and backoff, idempotency, timeouts, and driver-based extension. Use when touching anything under Services/Integration, Services/Ai, Services/Social, Services/Ads, app/Jobs, WebhookController, the Baileys controllers, or any code that calls an external API.
---

# External Integrations (POS)

This project talks to a lot of systems it does not control: WooCommerce, Shopify, Meta (Graph/Ads),
Google Ads, WhatsApp Cloud API, an unofficial WhatsApp bridge, and several AI providers. Everything in
here follows from one idea: **the remote system is untrusted, unreliable, and shop-scoped.**

Related skills: [laravel-security](../laravel-security/SKILL.md) (webhooks, secrets, SSRF),
[laravel-api](../laravel-api/SKILL.md) (endpoint shape), [laravel-performance](../laravel-performance/SKILL.md)
(keeping remote calls off the request path), [laravel-architecture](../laravel-architecture/SKILL.md)
(where the classes live).

## The map

```
app/Services/Integration/     WooCommerceService, WooCommerceProductSyncService, WooCommerceOrderSyncService
                              ShopifyService, ShopifyProductSyncService, ShopifyOrderSyncService
                              ImageDownloadService
app/Services/Ai/              AiManager + Contracts/AiContentGenerator + Drivers/{Qwen,Null,AbstractAiDriver}
app/Services/Social/          SocialPosterManager + Contracts/SocialPoster + Drivers/{Facebook,Instagram,Null}
app/Services/Ads/             MetaAdsService, GoogleAdsService
app/Services/                 WhatsAppService (Meta Cloud API), BaileysService (Node bridge)
app/Http/Controllers/         WebhookController (woocommerce + shopify)
app/Http/Controllers/Baileys/ WebhookController (bridge → Laravel), SessionController, InboxController, BroadcastController
app/Jobs/                     ProcessOrderWebhookJob, FetchEcommerceOrdersJob, SyncOrderStatusJob,
                              SyncProductsJob, SyncSingleProductJob, PublishCampaignPostJob
baileys-bridge/               standalone Node service (@whiskeysockets/baileys)
```

Config: `config/services.php` (Meta, Google Ads, WhatsApp Cloud, mail, Slack), `config/ai.php`,
`config/baileys.php`. **App-level credentials live in config; per-shop credentials live on the shop.**

## Credentials — per-shop and encrypted

There is no single global WooCommerce/Shopify account. Each shop stores its own credentials in
`shops.settings.integrations.{platform}` (a JSON `array` cast), accessed via
`$shop->getIntegrationConfig('woocommerce')` / `setIntegrationConfig()`.

**Individual secret values are encrypted with `Crypt`**, and decrypted at point of use:

```php
$config = $shop->getIntegrationConfig('woocommerce');
$consumerKey    = Crypt::decrypt($config['consumer_key']);
$consumerSecret = Crypt::decrypt($config['consumer_secret']);
```

Shopify stores `access_token` the same way (`ShopifyService` encrypts on write, decrypts on read).
Social/Ads tokens live on `social_accounts`, encrypted as a JSON blob via `Crypt::encryptString`.

Rules:
- Any new credential field is encrypted on write and decrypted only where the request is made.
- Wrap `Crypt::decrypt()` in a try/catch — a rotated `APP_KEY` or a plaintext legacy value throws, and
  the existing code returns a 500 "Configuration error" rather than leaking the exception.
- Never log a decrypted credential, never return one in a response, never put one in a job payload that
  isn't `ShouldBeEncrypted`.
- App-level OAuth creds (`META_APP_ID`, `GOOGLE_ADS_*`) belong in `config/services.php` read via
  `config()`, and their keys must be added to `.env.example`.

## Inbound webhooks

Routes are public by design (no session, no Sanctum) and live at the top of `routes/api.php`:

| Endpoint | Verification | Behaviour |
|---|---|---|
| `POST /api/webhooks/shopify/{shop:uuid}` | `X-Shopify-Hmac-Sha256` | fails closed — no secret configured → 400 |
| `POST /api/webhooks/woocommerce/{shop:uuid}` | `X-WC-Webhook-Signature` | fails closed → 400/401 |
| `POST /api/webhooks/baileys` | `X-Baileys-Signature` | fails closed → 401 |

Verification is `base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true))` compared with
`hash_equals()` — constant-time, over the **raw body**. Never reimplement this with `==`, and never
verify against `$request->all()` (re-encoded JSON won't match the signed bytes).

> **All three handlers now fail closed** — a missing secret is a 400, a bad signature a 401, and nothing is
> queued in either case. WooCommerce used to wrap verification in `if ($webhookSecret)`, silently accepting
> unauthenticated payloads for shops with no secret configured; that was fixed and is locked in by
> [tests/Feature/WebhookSignatureVerificationTest.php](../../../tests/Feature/WebhookSignatureVerificationTest.php).
> **Operational precondition:** every shop with a WooCommerce integration must now have an encrypted
> `webhook_secret` in `settings.integrations.woocommerce`, or its webhooks will be rejected. Don't
> "fix" a rejection by reintroducing the conditional.

Webhook handler rules:
- Verify first, **then** dispatch a queued job. Do no business work in the controller.
- Respond `200` fast; platforms retry aggressively on timeout and will duplicate your work.
- Log signature failures with shop and topic — never with the payload secrets or the raw body.
- Treat every payload field as untrusted input: validate before it reaches a model.

## Idempotency — assume every event arrives twice

Platforms redeliver. `ProcessOrderWebhookJob` gets this right: it looks up
`EcommerceOrder` by `(shop_id, platform, platform_order_id)` before creating. Copy that shape.

- Dedupe on the **platform's** identifier scoped to shop and platform, never on your own increment.
- `ProductEcommerceSync` (`product_ecommerce_sync` table) is the local↔remote map: `platform`,
  `platform_product_id`, `sync_status`, `sync_direction`, `last_synced_at`, `last_sync_attempt_at`,
  `last_sync_error`, `auto_sync`. Update it on every attempt — success *and* failure — so a failed sync
  is visible instead of silent.
- Guard against sync loops. `ProductObserver::$syncInProgress` is the existing latch: when a product is
  written *by* an inbound sync, the outbound sync must not re-fire. Any new two-way path needs the same
  protection.
- The scheduled campaign sweep in `routes/console.php` uses `withoutOverlapping()` and the job re-checks
  state before posting — do the same for any new scheduled dispatcher.

## Queued jobs — the only place external calls belong

**No external HTTP call in a controller, observer, or model event.** Dispatch a job.

The house pattern, from `ProcessOrderWebhookJob` / `SyncOrderStatusJob`:

```php
public int $timeout = 30;
public int $tries = 3;
public array $backoff = [5, 15, 30];   // seconds between retries

public function __construct(/* ... */)
{
    $this->onQueue('ecommerce');
}

public function failed(\Throwable $exception): void
{
    Log::error('Sync failed after all retries', ['shop_id' => ..., 'error' => $exception->getMessage()]);
}
```

- Always set `timeout`, `tries`, and `backoff` — a hung remote call otherwise blocks the worker.
- Named queues: `ecommerce` for sync/webhook work, `config('baileys.queue')` (default `baileys`) for the
  bridge. Use them; don't dump everything on `default`.
- Implement `failed()` so a job that exhausts retries leaves a trace (only two jobs do today).
- Keep the payload small — pass IDs, not hydrated models with relations.
- Fan out rather than looping: `SyncProductsJob` dispatches a `SyncSingleProductJob` per product to stay
  inside its timeout. Copy that when a batch could grow.
- Queue driver is `database`, so each dispatch is a DB write — batch sensibly.

## Outbound HTTP

```php
$response = Http::timeout(30)
    ->withHeaders(['X-Shopify-Access-Token' => $accessToken])
    ->get($url);

if (! $response->successful()) {
    // record on ProductEcommerceSync + throw so the job retries
}
```

- **Always set a timeout.** 5 `Http::withHeaders(...)` calls in `Services/Integration/` currently have
  none — if you touch one, add it. Existing convention: 10s for cheap reads, 30s for sync calls,
  `config('baileys.request_timeout')` (15s) for the bridge.
- There is **no `->retry()` anywhere** in `app/` — retries are handled at the job level. Don't mix both
  without thinking about `tries × retries` total attempts.
- Always check `$response->successful()`. Never assume a 200 body shape; the remote can and will change.
- Record the failure on `ProductEcommerceSync.last_sync_error` before rethrowing.
- **SSRF**: `ImageDownloadService` fetches remote URLs. Any code fetching a user- or platform-supplied
  URL must validate scheme/host and block private and metadata ranges.

## Driver-based subsystems (AI, Social)

Both follow the same manager + contract + drivers shape. Never hardcode a provider.

```php
$generator = app(AiManager::class)->forShop($shop);   // respects Shop.settings.ai.provider
```

`AiManager::forShop()` reads `Shop.settings.ai.provider`, falls back to `config('ai.default')` (`qwen`),
and **swallows an unknown provider by returning `NullDriver`**.

> ⚠️ **Trap:** `config/ai.php` declares `qwen`, `openai`, `claude`, `gemini`, and `null`, and
> `AiProvider` enum offers all five in the UI — but `AiManager::build()` only constructs `qwen` and
> `null`. Selecting OpenAI, Claude, or Gemini for a shop therefore produces **silently empty AI output**,
> not an error. Implement the missing driver (extend `AbstractAiDriver`, register in `build()` or via
> `AiManager::extend()`) before offering the option, or restrict the UI to implemented providers. If you
> implement the Anthropic driver, load the `claude-api` skill first — the model id pinned in
> `config/ai.php` is stale.

Social posting is the same: `SocialPosterManager` + `SocialPoster` contract + Facebook/Instagram/Null
drivers. Add a provider by adding a driver, not by branching inside a service.

## WhatsApp — two independent paths, don't conflate them

1. **Meta Cloud API** (official): `config('services.whatsapp')`, `WhatsAppService`, template-based, plus a
   custom notification channel in `app/Notifications/`.
2. **Baileys** (unofficial Node bridge): `config('baileys.*')`, `BaileysService` → HTTP → `baileys-bridge/`
   Node process; the bridge pushes events back to `POST /api/webhooks/baileys`. Session state lives on
   `BaileysSession` (`session_key`, status enum, QR flow). Auto-notifications are feature-flagged
   (`notify_on_sale`, `notify_on_ecommerce_order`) and numbers are normalised with
   `default_country_code` before JID resolution (`app/Support/BaileysJid.php`).

The bridge is a **separate process** (`cd baileys-bridge && npm start`) — if it's down, every call fails.
Degrade gracefully: a failed WhatsApp send must never roll back a completed sale.

## Failure philosophy

An integration failure must never break the POS. A shop must be able to sell when WooCommerce is down.

- Wrap external calls in try/catch; log with shop context; let the job retry.
- Never couple an external call to a business `DB::transaction` — commit the sale, then dispatch.
- Surface failures where staff can see them (`sync_status`, `last_sync_error`, alerts) instead of
  failing silently.

## Testing

`Http::fake()` for every remote call, `Queue::fake()`/`Bus::fake()` to assert dispatch, and explicit
tests for: valid signature accepted, invalid signature rejected, **missing secret rejected**, duplicate
webhook creates one record, and a failing remote marks `sync_status` without throwing to the user. See
[laravel-testing](../laravel-testing/SKILL.md). Never let a test hit a live API.

## Forbidden

- ❌ External HTTP in a controller, observer, or model event
- ❌ Webhook handling before signature verification, or verification that fails open
- ❌ `==` instead of `hash_equals()`; verifying against re-encoded JSON instead of the raw body
- ❌ Plaintext credentials in `shops.settings`, logs, or job payloads
- ❌ `Http::` without a timeout
- ❌ Jobs without `tries`/`backoff`, or unbounded batch loops in one job
- ❌ Creating records from a webhook without a platform-id dedupe check
- ❌ Hardcoding an AI or social provider instead of going through the manager
- ❌ Offering a driver in the UI that `build()` cannot construct
- ❌ Letting an integration failure roll back or block a sale
