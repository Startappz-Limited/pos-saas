# Baileys WhatsApp — Portable Integration Guide

A step-by-step recipe for adding multi-session WhatsApp (text, media, groups, channels, status) to **any Laravel 11/12 project** using a small Node.js bridge around `@whiskeysockets/baileys`.

This is the same architecture used in `baileys-bridge/` of this repo, abstracted so you can drop it into a new project in roughly an hour.

---

## 1. Architecture in one diagram

```
┌──────────────┐   HTTPS (Bearer)   ┌─────────────────────┐
│   Laravel    │ ─────────────────► │  Node.js Bridge     │
│  (your app)  │                    │  Express + Baileys  │
│              │ ◄───── HMAC ────── │  127.0.0.1:3025     │
└──────┬───────┘   webhook POST     └─────────┬───────────┘
       │ SSE                                  │ WebSocket
       ▼                                      ▼
   Browser inbox                        WhatsApp servers
```

- **Laravel** owns auth, persistence, UI, business logic.
- **Bridge** owns the Baileys socket, QR pairing, and media download/upload.
- They talk over **HTTP on localhost only** with a shared bearer token (Laravel → bridge) and an HMAC-SHA256 signature (bridge → Laravel webhook).

Why a separate Node process? Baileys is Node-only and stateful (long-lived WebSocket per WhatsApp account). PHP-FPM cannot host that.

---

## 2. Prerequisites

| Tool              | Min version   | Notes                                                |
| ----------------- | ------------- | ---------------------------------------------------- |
| PHP               | 8.2           | Laravel 11/12                                        |
| Composer          | 2.x           |                                                      |
| Node.js           | 18.17+        | Baileys uses native `crypto.subtle`                  |
| npm               | 9+            |                                                      |
| Storage           | ~500 MB       | Per WhatsApp account for sessions/auth state + media |
| One free TCP port | 3025 (or any) | Loopback only; never expose to the internet          |

If deploying on aaPanel/Plesk/cPanel, make sure you can run a long-lived Node process (PM2, supervisord, or systemd).

---

## 3. Database schema

Five tables. Generate with `php artisan make:migration`:

### 3.1 `baileys_sessions` — one row per paired WhatsApp account

| Column          | Type                           | Notes                                                                |
| --------------- | ------------------------------ | -------------------------------------------------------------------- |
| `id`            | bigInteger                     |                                                                      |
| `uuid`          | uuid, unique                   | Public identifier used in URLs                                       |
| `tenant_id`     | foreignId (shop/team/org/user) | Multi-tenant scope                                                   |
| `created_by`    | foreignId users, nullable      |                                                                      |
| `name`          | string(100)                    | Friendly label ("Reception")                                         |
| `session_key`   | string, unique                 | `tenant-<id>-<random>` — passed to bridge                            |
| `phone`         | string, nullable               | Filled after pairing                                                 |
| `status`        | string                         | enum: pending, qr_ready, connected, disconnected, logged_out, failed |
| `qr_code`       | longText, nullable             | Raw QR payload (string), rendered to `<img>` in Blade                |
| `qr_expires_at` | timestamp, nullable            |                                                                      |
| `connected_at`  | timestamp, nullable            |                                                                      |
| `last_seen_at`  | timestamp, nullable            |                                                                      |
| `meta`          | json, nullable                 | Bridge metadata, push name, etc.                                     |
| timestamps      |                                |                                                                      |

### 3.2 `baileys_chats` — per conversation

| Column               | Type                       | Notes                                          |
| -------------------- | -------------------------- | ---------------------------------------------- |
| `baileys_session_id` | foreignId                  |                                                |
| `tenant_id`          | foreignId                  | Denormalised for fast filters                  |
| `customer_id`        | foreignId, nullable        | Optional CRM link                              |
| `jid`                | string                     | `…@s.whatsapp.net` / `…@g.us` / `…@newsletter` |
| `name`               | string, nullable           |                                                |
| `type`               | string                     | private, group, channel                        |
| `unread_count`       | unsignedInteger, default 0 |                                                |
| `last_message_at`    | timestamp, nullable        | Used by SSE for incremental list updates       |
| timestamps           |                            |                                                |

Add unique index on `(baileys_session_id, jid)`.

### 3.3 `baileys_messages`

| Column                               | Type                      | Notes                                                  |
| ------------------------------------ | ------------------------- | ------------------------------------------------------ |
| `baileys_chat_id`                    | foreignId                 |                                                        |
| `baileys_session_id`                 | foreignId                 |                                                        |
| `wa_message_id`                      | string, nullable, indexed | WhatsApp's id (idempotency key)                        |
| `direction`                          | string                    | inbound / outbound                                     |
| `type`                               | string                    | text, image, video, audio, sticker, document, location |
| `status`                             | string                    | pending, sent, delivered, read, failed                 |
| `content`                            | text, nullable            | Text body or caption                                   |
| `media_url`                          | string, nullable          | `asset('storage/baileys/...')`                         |
| `media_mime`                         | string, nullable          |                                                        |
| `media_filename`                     | string, nullable          |                                                        |
| `payload`                            | json, nullable            | Raw message payload (without binary data)              |
| `sent_at`, `delivered_at`, `read_at` | timestamp, nullable       |                                                        |
| timestamps                           |                           |                                                        |

Index `(baileys_chat_id, id)`.

### 3.4 `baileys_contacts` (optional but useful for autocomplete)

`baileys_session_id`, `jid`, `name`, `push_name`, `verified_name`, `is_business`, timestamps. Unique on `(baileys_session_id, jid)`.

### 3.5 Spatie permissions (if you use them)

```
baileys.view
baileys.send
baileys.manage      // create/disconnect sessions
baileys.full-access
```

---

## 4. Config & env

### 4.1 `config/baileys.php`

```php
<?php

return [
    'bridge_url'     => env('BAILEYS_BRIDGE_URL', 'http://127.0.0.1:3025'),
    'bridge_token'   => env('BAILEYS_BRIDGE_TOKEN'),
    'webhook_secret' => env('BAILEYS_WEBHOOK_SECRET'),
    'request_timeout' => env('BAILEYS_REQUEST_TIMEOUT', 15),
];
```

### 4.2 `.env` (Laravel side)

```dotenv
BAILEYS_BRIDGE_URL=http://127.0.0.1:3025
BAILEYS_BRIDGE_TOKEN=$(openssl rand -hex 32)
BAILEYS_WEBHOOK_SECRET=$(openssl rand -hex 32)
```

### 4.3 `baileys-bridge/.env` (Node side)

```dotenv
BAILEYS_BRIDGE_PORT=3025
BAILEYS_BRIDGE_HOST=127.0.0.1
BAILEYS_BRIDGE_TOKEN=<same as Laravel>
BAILEYS_WEBHOOK_URL=http://your-app.test/api/webhooks/baileys
BAILEYS_WEBHOOK_SECRET=<same as Laravel>
BAILEYS_SESSIONS_DIR=./sessions
LOG_LEVEL=info
```

The two secrets must be **identical** in both `.env` files.

---

## 5. The Node bridge

Five small files inside `baileys-bridge/src/`:

| File                | Responsibility                                                    |
| ------------------- | ----------------------------------------------------------------- |
| `config.js`         | Reads env, exports immutable config object                        |
| `logger.js`         | Pino logger                                                       |
| `webhook.js`        | Signs + POSTs JSON to Laravel; axios with 30 s timeout, 64 MB cap |
| `sessionManager.js` | Creates/resumes Baileys sockets, handles events, persists auth    |
| `server.js`         | Express HTTP API the Laravel app calls                            |

### 5.1 `package.json`

```json
{
    "name": "baileys-bridge",
    "type": "commonjs",
    "main": "src/server.js",
    "scripts": {
        "start": "node src/server.js",
        "dev": "node --watch src/server.js"
    },
    "engines": { "node": ">=18.17" },
    "dependencies": {
        "@hapi/boom": "^10.0.1",
        "@whiskeysockets/baileys": "^6.7.8",
        "axios": "^1.7.7",
        "dotenv": "^16.4.5",
        "express": "^4.21.0",
        "pino": "^9.4.0",
        "qrcode": "^1.5.4"
    }
}
```

### 5.2 Bridge HTTP API

| Method | Path                            | Body                           | Notes                              |
| ------ | ------------------------------- | ------------------------------ | ---------------------------------- |
| POST   | `/sessions`                     | `{ session_key, name? }`       | Boots the WA socket, persists auth |
| GET    | `/sessions/:key`                |                                | Status                             |
| GET    | `/sessions/:key/qr`             |                                | `{ qr, expires_at }`               |
| DELETE | `/sessions/:key`                |                                | Logout + wipe                      |
| POST   | `/sessions/:key/messages/text`  | `{ to, text }`                 | `to` is a JID                      |
| POST   | `/sessions/:key/messages/media` | `{ to, type, url?, data?, … }` | Prefer base64 `data` over `url`    |
| POST   | `/sessions/:key/status`         | `{ type, text?, media? }`      | Story / status broadcast           |
| GET    | `/sessions/:key/groups`         |                                | List groups                        |
| GET    | `/sessions/:key/channels`       |                                | List channels (newsletter JIDs)    |

All requests must carry `Authorization: Bearer <BAILEYS_BRIDGE_TOKEN>`.

### 5.3 Critical bits people get wrong

1. **Persist auth state per session** — use `useMultiFileAuthState(path.join(sessionsDir, sessionKey))`. Do NOT regenerate per request.
2. **Never expose port 3025 to the internet.** Bind to `127.0.0.1`. Laravel reaches it over loopback.
3. **For inbound media, download bytes inside the bridge** (PHP can't decrypt WhatsApp's E2E media):
    ```js
    const { downloadMediaMessage } = require("@whiskeysockets/baileys");
    const buffer = await downloadMediaMessage(
        msg,
        "buffer",
        {},
        {
            reuploadRequest: sock.updateMediaMessage,
        },
    );
    payload.media_data = buffer.toString("base64");
    ```
4. **For outbound media, prefer inline base64** rather than `{ url: 'https://…' }`. The Baileys process must be able to fetch that URL otherwise. Inline is firewall-proof:
    ```js
    const buffer = source.data
        ? Buffer.from(source.data, "base64")
        : { url: source.url };
    await sock.sendMessage(jid, { image: buffer, caption });
    ```
5. **Bump axios + express body limits**: `express.json({ limit: '64mb' })` and axios `maxBodyLength`/`maxContentLength` to `64 * 1024 * 1024`. Otherwise videos blow up.
6. **Sign every webhook**:
    ```js
    const sig = crypto
        .createHmac("sha256", secret)
        .update(rawBody)
        .digest("hex");
    axios.post(url, body, { headers: { "X-Baileys-Signature": sig } });
    ```
7. **History sync off** unless you really want it: `syncFullHistory: false`. Saves bandwidth and CPU.

---

## 6. The Laravel side

### 6.1 Service wrapper — `app/Services/BaileysService.php`

Thin Guzzle/Http facade wrapper that does:

```php
Http::withToken(config('baileys.bridge_token'))
    ->timeout(config('baileys.request_timeout', 15))
    ->acceptJson()
    ->baseUrl(config('baileys.bridge_url'));
```

One method per bridge endpoint. Always returns `array{success: bool, message: string, response?: array}`.

### 6.2 Action classes — `app/Actions/Baileys/*`

Single-purpose, injected via the container:

- `StartBaileysSession` — creates the model row, calls bridge `/sessions`, transitions status.
- `DisconnectBaileysSession` — bridge DELETE, marks disconnected.
- `SendBaileysMessage` — refuses if session not connected, calls bridge, persists `BaileysMessage` row with `wa_message_id` from response.
- `SendBaileysMediaMessage` — same pattern, accepts `extras['data']` (base64) so the controller can inline upload bytes.

### 6.3 Webhook controller

One route, no auth middleware (HMAC instead):

```php
Route::post('/api/webhooks/baileys', WebhookController::class)
    ->middleware('throttle:300,1');   // generous, single bridge talks to us
```

In the controller:

```php
$rawBody  = $request->getContent();
$expected = hash_hmac('sha256', $rawBody, config('baileys.webhook_secret'));
abort_unless(hash_equals($expected, $request->header('X-Baileys-Signature', '')), 401);

match ($request->input('event')) {
    'qr.update'         => $this->handleQr($request->all()),
    'connection.update' => $this->handleConnection($request->all()),
    'message.received'  => $this->handleInboundMessage($request->all()),
    'message.status'    => $this->handleStatus($request->all()),
    default             => null,
};
```

Inside `handleInboundMessage`, decode `media_data` → write to `Storage::disk('public')->put("baileys/{$session->uuid}/in/{$id}.{$ext}", $bytes)` → set `media_url = asset('storage/'.$relative)`.

Strip `media_data` from the row's `payload` JSON to keep the database lean.

### 6.4 Real-time inbox via SSE

Don't reach for Reverb/Pusher/WebSockets. Server-Sent Events are dirt cheap, work on any shared host with Nginx, and need no JS library.

```php
public function stream(BaileysChat $chat): StreamedResponse
{
    return response()->stream(function () use ($chat) {
        $deadline = microtime(true) + 50;          // 50 s, then client reconnects
        $lastId   = (int) request('since_message_id', 0);

        while (microtime(true) < $deadline) {
            // Push new inbound rows
            BaileysMessage::where('baileys_chat_id', $chat->id)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->each(function ($m) use (&$lastId) {
                    echo "event: messages\ndata: " . json_encode($m) . "\n\n";
                    $lastId = $m->id;
                });

            // Push outbound status changes (single-tick → double-tick → blue)
            // … query updated_at > $sinceUpdatedAt …

            @ob_flush(); @flush();
            usleep(1_000_000);                      // 1 s tick
        }
    }, 200, [
        'Content-Type'      => 'text/event-stream',
        'Cache-Control'     => 'no-cache, no-store, must-revalidate',
        'X-Accel-Buffering' => 'no',                // critical for Nginx
        'Connection'        => 'keep-alive',
    ]);
}
```

Client side, plain vanilla:

```js
const es = new EventSource(`${streamUrl}?since_message_id=${lastId}`);
es.addEventListener("messages", (e) => appendInbound(JSON.parse(e.data)));
es.addEventListener("status", (e) => updateTick(JSON.parse(e.data)));
document.addEventListener("visibilitychange", () => {
    if (document.hidden) {
        es.close();
    }
});
```

### 6.5 Nginx config snippet (aaPanel / Plesk / Forge)

Add inside the `server` block, **before** the catch-all `location /` rule:

```nginx
location ~ ^/baileys/inbox/[0-9]+/stream$ {
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 3600s;
    gzip off;
    fastcgi_buffering off;
    fastcgi_read_timeout 3600s;
    add_header X-Accel-Buffering no;
    try_files $uri /index.php?$query_string;
}
location /baileys/inbox/ {
    client_max_body_size 32M;
}
```

Without `proxy_buffering off` / `X-Accel-Buffering: no`, Nginx will hold the SSE response and the inbox will look frozen.

---

## 7. Pairing UX

1. User clicks "New WhatsApp" → `POST /baileys/sessions` (Laravel).
2. Action creates `BaileysSession` (status `pending`), calls bridge `POST /sessions`.
3. Bridge boots socket; on first `connection.update` with a `qr` value, POSTs `qr.update` webhook to Laravel.
4. Laravel writes `qr_code` to the session.
5. UI polls `GET /baileys/sessions/{uuid}` every 2 s (or use SSE) and renders `<img src="data:image/png;base64,…">` made from the QR string.
6. User opens WhatsApp → Linked Devices → Link a Device → scans.
7. Bridge fires `connection.update` with `connection: 'open'` → webhook → Laravel flips status to `connected`.
8. From this point forward all sends are fire-and-forget against the bridge.

---

## 8. Production hardening checklist

- [ ] Bridge supervised by **PM2** (`pm2 start src/server.js --name baileys`) or systemd. Auto-restart on crash.
- [ ] Bridge bound to `127.0.0.1` only. Confirm with `lsof -iTCP:3025`.
- [ ] Both secrets in a vault, not committed.
- [ ] `php artisan storage:link` run on deploy.
- [ ] Daily backup of `baileys-bridge/sessions/` (otherwise re-pairing required after restore).
- [ ] Send queue: wrap `SendBaileysMessage` in a `ShouldQueue` job for bulk sends — WhatsApp will rate-limit aggressive senders and may ban the account.
- [ ] Throttle: never blast more than ~20 msgs/min/account when warming a fresh number; ~60/min on aged numbers.
- [ ] Log scrubber: don't write message bodies to logs in production.
- [ ] Multi-tenancy isolation: every query touches `tenant_id` or a session belonging to the tenant.

---

## 9. Testing

Use **Pest 4** with `Http::fake()` for the bridge. Pattern:

```php
beforeEach(function () {
    config()->set('baileys.bridge_url', 'http://bridge.test');
    config()->set('baileys.bridge_token', 'test-token');
    config()->set('baileys.webhook_secret', 'shh');
});

it('sends a text message via the bridge', function () {
    Http::fake([
        'bridge.test/sessions/*/messages/text' => Http::response(['wa_message_id' => 'WAID-1']),
    ]);

    $session = BaileysSession::factory()->connected()->create();
    $message = app(SendBaileysMessage::class)->execute($session, '15551234567@s.whatsapp.net', 'Hi');

    expect($message->wa_message_id)->toBe('WAID-1');
    Http::assertSent(fn ($r) => $r['text'] === 'Hi');
});
```

For webhook tests, compute the HMAC with the same secret and post the body verbatim.

For SSE: assert `Content-Type` contains `text/event-stream` and `X-Accel-Buffering: no`. Don't try to consume the stream in a unit test — too flaky.

---

## 10. Common pitfalls

| Symptom                                     | Cause                                                         | Fix                                                             |
| ------------------------------------------- | ------------------------------------------------------------- | --------------------------------------------------------------- |
| QR never appears                            | Bridge can't write to `sessions/` dir                         | `chown` it to the Node user, 0700                               |
| QR works locally, fails on shared hosting   | Outbound websocket blocked by firewall                        | Whitelist `*.whatsapp.net:443`                                  |
| Inbox stays frozen, no real-time            | Nginx buffering SSE                                           | Add `proxy_buffering off` + `X-Accel-Buffering: no` (§6.5)      |
| Stickers/images received but show as broken | Bridge didn't call `downloadMediaMessage`                     | Decode in bridge, ship base64 (§5.3 #3)                         |
| Outbound image fails sometimes              | Bridge fetched `asset()` URL behind firewall/DNS issue        | Inline base64 (§5.3 #4)                                         |
| Only one tick ever shows                    | SSE never re-emits status changes                             | Add a `status` event in `stream()` querying `updated_at` deltas |
| `MessageCounterError: Key used already`     | Same WA account paired in another linked device that re-keyed | Non-fatal; re-pair if it persists                               |
| Bridge crashes on restart with `EADDRINUSE` | Old process still holding port                                | `lsof -ti tcp:3025 \| xargs kill -9`                            |
| `ViteException: Unable to locate file`      | Frontend assets not built                                     | `npm run build`                                                 |
| Webhook returns 401                         | Secret mismatch between Node `.env` and Laravel `.env`        | Make them identical, restart bridge                             |

---

## 11. License & legal note

`@whiskeysockets/baileys` is an **unofficial reverse-engineered** WhatsApp Web client. WhatsApp may ban the linked phone number at any time, especially for spam-like patterns. For business use cases that need an SLA, consider the official **WhatsApp Business Cloud API** (Meta) instead. This bridge is best for internal tools, CRM integrations, and small-scale automation where ban risk is acceptable.

---

## 12. File map cheat-sheet

```
your-project/
├── app/
│   ├── Actions/Baileys/
│   │   ├── StartBaileysSession.php
│   │   ├── DisconnectBaileysSession.php
│   │   ├── SendBaileysMessage.php
│   │   └── SendBaileysMediaMessage.php
│   ├── Enums/
│   │   ├── BaileysSessionStatus.php
│   │   ├── BaileysChatType.php
│   │   ├── BaileysMessageDirection.php
│   │   ├── BaileysMessageStatus.php
│   │   └── BaileysMessageType.php
│   ├── Http/Controllers/Baileys/
│   │   ├── SessionController.php
│   │   ├── InboxController.php       ← also hosts stream()
│   │   ├── BroadcastController.php
│   │   └── WebhookController.php
│   ├── Models/{BaileysSession,BaileysChat,BaileysMessage,BaileysContact}.php
│   └── Services/BaileysService.php
├── baileys-bridge/
│   ├── package.json
│   ├── .env
│   ├── sessions/                     ← gitignored
│   └── src/{config,logger,webhook,sessionManager,server}.js
├── config/baileys.php
├── database/migrations/2024_…_create_baileys_*.php
├── resources/views/baileys/{sessions,inbox,broadcast}/*.blade.php
└── tests/Feature/Baileys/*.php
```

That's everything you need to clone the pattern into a new Laravel project.
