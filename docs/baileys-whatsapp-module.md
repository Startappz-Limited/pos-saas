# Baileys (Unofficial WhatsApp) Module

This document explains how the unofficial WhatsApp integration was implemented in
this Laravel application using [`@whiskeysockets/baileys`](https://github.com/WhiskeySockets/Baileys),
and how to reproduce the same setup in any other Laravel project.

> **Why "unofficial"?** Baileys talks to WhatsApp's WebSocket protocol directly via
> a paired phone (QR-code login), the same way WhatsApp Web does. It does not use
> the official **WhatsApp Cloud API** and is intentionally kept fully isolated
> from any official integration in the same codebase.

---

## 1. Architecture at a glance

```
┌─────────────────────────┐         HTTPS + Bearer token        ┌────────────────────────────┐
│   Laravel application   │ ──────── BaileysService ───────────►│   Node.js Baileys bridge   │
│  (controllers, models,  │                                     │  (Express + Baileys SDK,   │
│   queues, Blade UI)     │◄──── HMAC-signed webhooks ───────── │   pairs your WA phone)     │
└─────────────────────────┘                                     └────────────────────────────┘
            │                                                                │
            │ writes                                                         │ persists creds in
            ▼                                                                ▼ ./sessions/<key>/
   MySQL: baileys_sessions, baileys_chats,                          file-based MultiFileAuthState
   baileys_contacts, baileys_messages
```

Two processes:

1. **Laravel app** — owns DB, UI, business rules, queues, security boundary.
2. **Node bridge** (`baileys-bridge/`) — a thin process whose only job is to
   speak the WhatsApp protocol and translate it into HTTP requests/webhooks.

They communicate over plain HTTP, authenticated by a **bridge token** (Laravel →
Node) and an **HMAC-SHA256 signature** (Node → Laravel).

---

## 2. Files & directories created in this project

### Laravel side

| File | Purpose |
| --- | --- |
| `config/baileys.php` | All Baileys config (bridge URL, token, webhook secret, queue, feature flags). |
| `app/Enums/BaileysSessionStatus.php` | `Disconnected`, `Connecting`, `QrReady`, `Connected`, `Failed`. |
| `app/Enums/BaileysChatType.php` | `Private`, `Group`, `Channel`, `Broadcast`, `Status` + `fromJid()` helper. |
| `app/Enums/BaileysMessageDirection.php` | `Inbound`, `Outbound`. |
| `app/Enums/BaileysMessageStatus.php` | `Pending`, `Sent`, `Delivered`, `Read`, `Failed`. |
| `app/Enums/BaileysMessageType.php` | `Text`, `Image`, `Video`, `Audio`, `Document`, `Sticker`, `Location`, `Contact`. |
| `database/migrations/..._create_baileys_sessions_table.php` | One row per paired phone (per shop). |
| `database/migrations/..._create_baileys_chats_table.php` | Conversations (private/group/channel). |
| `database/migrations/..._create_baileys_contacts_table.php` | Optional cache of WA contacts. |
| `database/migrations/..._create_baileys_messages_table.php` | All inbound + outbound messages. |
| `database/migrations/..._add_phone_to_baileys_chats_table.php` | Lets us map opaque `@lid` JIDs to a real phone number + name. |
| `app/Models/BaileysSession.php` | Auto-generates UUID + `session_key`; `connected()` scope; `markConnected/Disconnected`. |
| `app/Models/BaileysChat.php` | Belongs to session+shop, hasMany messages. |
| `app/Models/BaileysContact.php` | Optional, used for contact directory. |
| `app/Models/BaileysMessage.php` | `markAsDelivered/Read/Failed`. |
| `database/factories/Baileys*Factory.php` | With `connected`, `qrReady`, `inbound`, `sent`, `failed`, `group`, `channel` states. |
| `app/Support/BaileysJid.php` | `fromPhone(string): ?string` — strips non-digits, returns `…@s.whatsapp.net`. |
| `app/Services/BaileysService.php` | HTTP client → bridge (`startSession`, `sendText`, `sendMedia`, `sendStatus`, `listGroups`, `listChannels`). |
| `app/Actions/Baileys/StartBaileysSession.php` | Creates DB row + tells bridge to start a socket. |
| `app/Actions/Baileys/DisconnectBaileysSession.php` | Logs out + wipes credentials. |
| `app/Actions/Baileys/SendBaileysMessage.php` | Persists outbound row + dispatches send. |
| `app/Actions/Baileys/SendBaileysMediaMessage.php` | Same, for media. |
| `app/Actions/Baileys/UpdateBaileysStatus.php` | Posts a WA Status (story). |
| `app/Actions/Baileys/NotifyCustomerOfSale.php` | Phase-2 hook — sends purchase confirmation. |
| `app/Actions/Baileys/NotifyCustomerOfEcommerceOrder.php` | Phase-2 hook — sends order confirmation. |
| `app/Http/Controllers/Baileys/SessionController.php` | List/create/show/delete sessions, render QR. |
| `app/Http/Controllers/Baileys/InboxController.php` | Chat list + thread + send + start-new-chat + map-`@lid`. |
| `app/Http/Controllers/Baileys/BroadcastController.php` | Group / channel / status broadcasts. |
| `app/Http/Controllers/Baileys/WebhookController.php` | HMAC-verified ingest of bridge events. |
| `app/Policies/BaileysSessionPolicy.php` | `viewAny/view` → `baileys.view`; `create/delete` → `baileys.manage`. |
| `routes/web.php` | `baileys.sessions.*`, `baileys.inbox.*`, `baileys.broadcast.*` (auth-protected). |
| `routes/api.php` | `POST /api/webhooks/baileys` → `webhooks.baileys`. |
| `resources/views/baileys/sessions/*` | Sessions list, create-with-QR, show. |
| `resources/views/baileys/inbox/index.blade.php` | Two-column inbox UI (chats + thread). |
| `resources/views/baileys/broadcast/show.blade.php` | Group/channel/status broadcast forms. |
| `resources/views/partials/sidebar.blade.php` | "Baileys" nav block (Sessions / Inbox / Broadcast). |
| `database/seeders/PermissionSeeder.php` | Adds `baileys.view`, `baileys.send`, `baileys.manage`, `baileys.full-access`. |
| `tests/Feature/Baileys/*Test.php` | Pest tests covering webhook signing, send, start-chat, map-chat. |

### Node bridge (`baileys-bridge/`)

```
baileys-bridge/
├── package.json          # express, @whiskeysockets/baileys, axios, dotenv, pino, qrcode
├── src/
│   ├── config.js         # reads .env (PORT, BRIDGE_TOKEN, LARAVEL_WEBHOOK_URL/SECRET, SESSIONS_DIR)
│   ├── logger.js         # pino logger
│   ├── webhook.js        # sendWebhook(event, data) — JSON + HMAC-SHA256 header
│   ├── sessionManager.js # Map<sessionKey, sock>; startSession, logout, sendText, sendMedia,
│   │                     # sendStatus, listGroups, listChannels; emits webhooks for QR, connect,
│   │                     # disconnect, message.received, message.status
│   └── server.js         # Express endpoints, Bearer-token auth middleware
├── sessions/             # MultiFileAuthState — one folder per session_key (gitignored)
├── .env / .env.example   # PORT, BRIDGE_TOKEN, LARAVEL_WEBHOOK_URL, LARAVEL_WEBHOOK_SECRET, ...
└── README.md             # Endpoints + webhook event schema
```

---

## 3. Wire-level contract

### 3.1 Bridge HTTP API (Laravel → Node)

All requests carry `Authorization: Bearer <BRIDGE_TOKEN>` (except `/health`).

| Method | Path | Body | Purpose |
| --- | --- | --- | --- |
| GET | `/health` | — | Liveness probe. |
| POST | `/sessions` | `{ session_key, label? }` | Start (or resume) a session. Triggers QR webhook. |
| GET | `/sessions/:key` | — | Current state. |
| GET | `/sessions/:key/qr` | — | Latest QR data URL (also pushed via webhook). |
| DELETE | `/sessions/:key` | — | `sock.logout()` + wipe creds. |
| POST | `/sessions/:key/messages/text` | `{ to, text }` | Send text message. |
| POST | `/sessions/:key/messages/media` | `{ to, type, url, caption?, filename? }` | Send media. |
| POST | `/sessions/:key/status` | `{ type, text\|url, caption? }` | Post WA Status. |
| GET | `/sessions/:key/groups` | — | List joined groups. |
| GET | `/sessions/:key/channels` | — | List subscribed newsletters/channels. |

### 3.2 Webhooks (Node → Laravel)

```
POST  /api/webhooks/baileys
Headers: X-Baileys-Signature: hex(hmac_sha256(BRIDGE_WEBHOOK_SECRET, raw_body))
Body:    { "event": "...", "data": { "session_key": "...", ... } }
```

Events:

| Event | Payload (under `data`) |
| --- | --- |
| `session.qr` | `qr` (data URL), `expires_at` |
| `session.connected` | `jid`, `phone_number`, `display_name` |
| `session.disconnected` | `reason` |
| `message.received` | `wa_message_id`, `chat_jid`, `sender_jid`, `sender_phone`, `type`, `content`, `media_url`, `media_mime`, `media_filename`, `payload`, `timestamp` (ISO) |
| `message.status` | `wa_message_id`, `status` (`delivered`/`read`/`failed`) |

Laravel verifies the HMAC, looks up the session by `session_key`, and dispatches
to one of the `handleQr/handleConnected/handleDisconnected/handleInboundMessage/handleStatusUpdate`
methods on `WebhookController`.

---

## 4. Security model

* **Bridge → Internet:** the bridge **must not** be exposed publicly. Bind it to
  `127.0.0.1` (or to a private network only Laravel can reach). The `BRIDGE_TOKEN`
  is the only thing protecting `sendText`, etc.
* **Laravel → Internet:** `/api/webhooks/baileys` is publicly reachable, but every
  request is rejected unless the `X-Baileys-Signature` HMAC matches the shared
  `BAILEYS_WEBHOOK_SECRET`. If the secret is empty, the controller refuses **all**
  webhooks (fail-closed).
* **Authorization:** `BaileysSessionPolicy` gates UI/controller access via Spatie
  permissions (`baileys.view`, `baileys.send`, `baileys.manage`).
* **Multi-tenancy:** every `baileys_*` row carries `shop_id`, and the session is
  the source of truth — chats and messages always inherit `shop_id` from their
  session.

---

## 5. Key design decisions

1. **Two processes, one boundary.** Baileys is a Node-only library; rather than
   shoehorn it into PHP via FFI/sidecar, a tiny Node service exposes the minimum
   surface Laravel needs. This keeps Laravel free of WA-protocol concerns.
2. **Stateless requests, stateful socket.** Laravel never holds the WA socket;
   it just keys every call by `session_key`. The bridge can be restarted and
   sessions re-resumed from the on-disk `MultiFileAuthState` folders.
3. **Everything is auditable in MySQL.** Both sent and received messages are
   persisted to `baileys_messages` with status transitions, so the inbox UI is
   just a regular Eloquent query — no live socket needed.
4. **Isolated from the official WhatsApp Cloud module.** Separate config file,
   separate tables (`baileys_*`), separate queue (`baileys`), separate sidebar
   entry. The official module (`App\Services\WhatsAppService`) is untouched.
5. **`@lid` mapping.** WhatsApp now hands out opaque `@lid` JIDs for some
   contacts. The `phone` column on `baileys_chats` lets an operator map an
   `@lid` chat to a real phone number + display name from the inbox UI.
6. **HMAC-signed webhooks.** Avoids needing a VPN/private network between bridge
   and Laravel — the secret is enough.
7. **Queued sends (optional).** `BAILEYS_QUEUE=baileys` lets you push outbound
   sends onto a dedicated queue worker so HTTP requests stay snappy.

---

## 6. End-to-end flows

### 6.1 Pairing a new phone

1. Admin clicks **Sessions → New** → `SessionController@store` calls
   `StartBaileysSession`.
2. Action inserts a `baileys_sessions` row (status `Connecting`, auto-generated
   `session_key`) and `BaileysService::startSession($key, $label)` POSTs to the
   bridge.
3. Bridge calls `useMultiFileAuthState('sessions/<key>/')`, opens the WA socket,
   and on `connection.update` with a `qr` payload posts `session.qr` back.
4. `WebhookController::handleQr` stores the QR data URL on the session row.
5. The Blade page polls `sessions.show` and renders the QR. The user scans it
   with WhatsApp on their phone.
6. Bridge fires `session.connected` → `markConnected()` flips status to
   `Connected` and stores `jid`/`phone_number`/`display_name`.

### 6.2 Sending a text

1. User submits the inbox form → `InboxController@send` calls `SendBaileysMessage`.
2. Action creates an outbound `baileys_messages` row (`status=Pending`) and
   `BaileysService::sendText($session, $jid, $text)`.
3. Bridge calls `sock.sendMessage(jid, { text })`, returns the `wa_message_id`.
4. Action updates the row with the `wa_message_id` and flips it to `Sent`.
5. WhatsApp later pushes status updates → bridge emits `message.status` →
   Laravel updates the row to `Delivered`/`Read`/`Failed`.

### 6.3 Receiving a message

1. WA pushes `messages.upsert` to the bridge.
2. Bridge extracts type/content/media, derives `sender_phone` from the JID,
   slims the raw payload, and POSTs `message.received`.
3. `WebhookController::handleInboundMessage` `firstOrCreate`s the chat,
   inserts a `baileys_messages` row, and bumps `last_message_at`,
   `last_message_preview`, `unread_count` on the chat.
4. The inbox UI shows it next refresh.

---

## 7. Reproducing this in another Laravel project

The whole thing is a copy-paste-and-rename exercise. Below is the minimal
recipe.

### 7.1 Prerequisites

* PHP 8.2+ / Laravel 11 or 12.
* Node 18+.
* MySQL or PostgreSQL.
* Redis (only if you queue sends).
* A phone with WhatsApp installed.

### 7.2 Steps

1. **Copy the Node bridge.**

   ```bash
   cp -R baileys-bridge/ /path/to/new-project/baileys-bridge/
   cd /path/to/new-project/baileys-bridge
   npm install
   cp .env.example .env
   # generate two 32-byte hex secrets:
   openssl rand -hex 32   # → BRIDGE_TOKEN
   openssl rand -hex 32   # → LARAVEL_WEBHOOK_SECRET
   # set LARAVEL_WEBHOOK_URL=http://your-app.test/api/webhooks/baileys
   ```

2. **Add `config/baileys.php`** (mirror this repo's file):

   ```php
   return [
       'bridge_url'      => env('BAILEYS_BRIDGE_URL', 'http://127.0.0.1:3025'),
       'bridge_token'    => env('BAILEYS_BRIDGE_TOKEN'),
       'webhook_secret'  => env('BAILEYS_WEBHOOK_SECRET'),
       'request_timeout' => (int) env('BAILEYS_REQUEST_TIMEOUT', 15),
       'queue'           => env('BAILEYS_QUEUE', 'baileys'),
       'features' => [
           'notify_on_sale'            => (bool) env('BAILEYS_NOTIFY_ON_SALE', false),
           'notify_on_ecommerce_order' => (bool) env('BAILEYS_NOTIFY_ON_ECOMMERCE_ORDER', false),
       ],
   ];
   ```

3. **Add the `.env` keys** (use the same secrets as the bridge):

   ```env
   BAILEYS_BRIDGE_URL=http://127.0.0.1:3025
   BAILEYS_BRIDGE_TOKEN=<same as bridge>
   BAILEYS_WEBHOOK_SECRET=<same as bridge>
   BAILEYS_REQUEST_TIMEOUT=15
   BAILEYS_QUEUE=baileys
   ```

4. **Copy / re-create the migrations** (`baileys_sessions`, `baileys_chats`,
   `baileys_contacts`, `baileys_messages`, plus the `phone`-on-chats migration).
   Ensure every table has a `shop_id` (or your own tenant FK), `created_at`,
   `updated_at`. Add the unique `(baileys_session_id, jid)` index on chats.
   `php artisan migrate`.

5. **Copy the enums, models, factories, and `App\Support\BaileysJid`.**
   Adjust namespaces only — the logic is generic.

6. **Copy `App\Services\BaileysService`.** Verify it uses
   `Http::asJson()->acceptJson()->timeout(...)->baseUrl(...)->withToken(...)`.

7. **Copy the actions** (`StartBaileysSession`, `DisconnectBaileysSession`,
   `SendBaileysMessage`, `SendBaileysMediaMessage`, `UpdateBaileysStatus`).
   These wrap the service and write to the DB.

8. **Copy the controllers + Blade views** under `App\Http\Controllers\Baileys`
   and `resources/views/baileys/`. The views use Bootstrap 5 + Iconify; swap
   the icon component if your project uses something else.

9. **Register routes** in `routes/web.php`:

   ```php
   Route::middleware(['auth'])->prefix('baileys')->name('baileys.')->group(function () {
       Route::resource('sessions', SessionController::class)->except(['edit', 'update']);
       Route::get('inbox',                                 [InboxController::class, 'index'])->name('inbox.index');
       Route::post('inbox/start/{session:uuid}',           [InboxController::class, 'startChat'])->name('inbox.start');
       Route::post('inbox/{chat}/map',                     [InboxController::class, 'mapChat'])->name('inbox.map');
       Route::post('inbox/{chat}/send',                    [InboxController::class, 'send'])->name('inbox.send');
       Route::get('broadcast',                             [BroadcastController::class, 'show'])->name('broadcast.show');
       Route::post('broadcast/{session}/group',            [BroadcastController::class, 'group'])->name('broadcast.group');
       Route::post('broadcast/{session}/channel',          [BroadcastController::class, 'channel'])->name('broadcast.channel');
       Route::post('broadcast/{session}/status',           [BroadcastController::class, 'status'])->name('broadcast.status');
   });
   ```

   And in `routes/api.php`:

   ```php
   Route::post('webhooks/baileys', WebhookController::class)->name('webhooks.baileys');
   ```

10. **Register the policy** in `AppServiceProvider::boot()`:

    ```php
    Gate::policy(BaileysSession::class, BaileysSessionPolicy::class);
    ```

11. **Seed permissions**: `baileys.view`, `baileys.send`, `baileys.manage`,
    `baileys.full-access`. Attach them to whatever role you use for admins.

12. **Add a sidebar / nav entry** linking to `baileys.sessions.index`,
    `baileys.inbox.index`, `baileys.broadcast.show`.

13. **(Optional) Phase-2 hooks.** Wherever you finalize a sale or process an
    ecommerce order, call:

    ```php
    if (config('baileys.features.notify_on_sale')) {
        try {
            app(NotifyCustomerOfSale::class)->execute($sale);
        } catch (\Throwable $e) {
            Log::warning('Baileys sale notify failed', ['e' => $e->getMessage()]);
        }
    }
    ```

14. **Run the bridge** (dev):

    ```bash
    cd baileys-bridge && npm run dev
    ```

    For production, supervise it (systemd, pm2, or Supervisor):

    ```ini
    [program:baileys-bridge]
    command=node src/server.js
    directory=/var/www/your-app/baileys-bridge
    autostart=true
    autorestart=true
    user=www-data
    environment=NODE_ENV=production
    ```

15. **Pair your phone:** open `/baileys/sessions/create`, scan the QR.

### 7.3 Smoke test

```bash
# bridge alive?
curl -s http://127.0.0.1:3025/health
# {"ok":true,"time":"..."}

# webhook reachable?
curl -s -X POST http://your-app.test/api/webhooks/baileys \
     -H 'Content-Type: application/json' -d '{}'
# {"message":"Invalid signature"}   ← good, fail-closed
```

Then send yourself a WhatsApp message — it should appear in the inbox.

---

## 8. Operational notes

* **QR refresh:** WhatsApp QR codes expire ~60s. The bridge re-emits a new QR on
  every refresh until the user scans.
* **Logged-out sessions:** when WA logs you out (e.g. you removed the device),
  the bridge wipes `sessions/<key>/` so the next pair starts clean.
* **Re-pairing:** delete the session in Laravel UI → bridge logs out + wipes
  creds → recreate.
* **Backups:** the bridge's `sessions/` folder *is* the credentials store. If
  you rebuild the bridge host, restore that folder to keep sessions paired
  without a new QR scan.
* **Rate limits:** Baileys has none of its own, but WhatsApp can ban numbers
  that send too aggressively. Throttle outbound sends in your queue worker.

### 8.1 Disk usage — read this before onboarding more clients

A linked phone receives far more than customer chats: every contact's WhatsApp
**status** (photo/video, posted daily), every **group** and **channel** post.
Early versions of this module downloaded and stored *all* of it, forever, which
filled a production disk with ~20GB of other people's status videos.

Three layers now bound that, and all three matter:

1. **The bridge refuses to download it** (`baileys-bridge/.env`) — the only layer
   that saves bandwidth and RAM as well as disk:
   * `INGEST_STATUS`, `INGEST_NEWSLETTERS`, `INGEST_BROADCASTS` — default `false`.
     Those chats never reach Laravel at all.
   * `MEDIA_DOWNLOAD_TYPES` — default `image,audio,document`. **`video` and
     `sticker` are deliberately excluded**; video is the single biggest disk
     consumer.
   * `MEDIA_MAX_BYTES` — default 5MB, checked against WhatsApp's declared size
     *before* the download and against the real buffer after it.
   * `MEDIA_FROM_GROUPS` — default `false`.
2. **Laravel enforces the same limits server-side** (`config/baileys.php` →
   `media`), so a bridge with a stale or permissive config still cannot fill the
   disk. `BAILEYS_MEDIA_SHOP_QUOTA_BYTES` (default 2GB) is the **per-tenant
   ceiling**: past it, messages still arrive but their media is dropped and a
   warning is logged. This is what stops one client's phone from taking down the
   server for everyone.
3. **Retention** — `baileys:prune-media` runs daily at 03:15 and deletes media
   older than `BAILEYS_MEDIA_RETENTION_DAYS` (default 30). Messages and captions
   survive; only the files go.

A skipped message is still recorded, with the reason in
`baileys_messages.payload.media_skipped` (`too_large`, `chat_type_not_allowed`,
`quota_exceeded`, `type_not_allowed`, `group_media_disabled`, `download_failed`).

**Manual operations:**

```bash
# What would be cleaned, without touching anything
php artisan baileys:prune-media --dry-run --orphans

# Reclaim now, aggressively (7-day window), including files no message references
php artisan baileys:prune-media --days=7 --orphans

# One tenant only
php artisan baileys:prune-media --shop=12

# Who is using the disk?
du -sh storage/app/public/baileys/*
```

**Log rotation is a separate trap.** Two logs will grow without bound if left
alone, independently of any media:

* Laravel: use `LOG_CHANNEL=daily` (+ `LOG_DAILY_DAYS`) and `LOG_LEVEL=warning`
  in production. With the default `single` channel, `laravel.log` is one file
  that is never rotated.
* The bridge: keep `LOG_LEVEL=info` or `warn` (`debug` logs full message
  payloads) and rotate the process log — e.g. `pm2 install pm2-logrotate`.

---

## 9. Related code references

* Bridge HTTP server — [baileys-bridge/src/server.js](../baileys-bridge/src/server.js)
* Bridge session lifecycle — [baileys-bridge/src/sessionManager.js](../baileys-bridge/src/sessionManager.js)
* HMAC webhook sender — [baileys-bridge/src/webhook.js](../baileys-bridge/src/webhook.js)
* Laravel HTTP client — [app/Services/BaileysService.php](../app/Services/BaileysService.php)
* Webhook ingest — [app/Http/Controllers/Baileys/WebhookController.php](../app/Http/Controllers/Baileys/WebhookController.php)
* Inbox UI + `@lid` mapping — [resources/views/baileys/inbox/index.blade.php](../resources/views/baileys/inbox/index.blade.php)
* JID helper — [app/Support/BaileysJid.php](../app/Support/BaileysJid.php)
* Policy — [app/Policies/BaileysSessionPolicy.php](../app/Policies/BaileysSessionPolicy.php)

---

## 10. Disclaimer

Baileys is **not** an official WhatsApp API. WhatsApp may suspend or ban numbers
that automate messaging. Use it only on numbers you own and accept the risk.
For production / regulated traffic, prefer the official WhatsApp Cloud API
(this codebase keeps both modules side-by-side and isolated for exactly that
reason).
