# Baileys Bridge

Tiny Node.js HTTP bridge that wraps [`@whiskeysockets/baileys`](https://github.com/whiskeysockets/Baileys)
so the Laravel app can talk to WhatsApp Web (unofficial) over a stable REST surface.

This is **not** the official WhatsApp Cloud API integration. The official integration
(`App\Services\WhatsAppService`, `whatsapp_messages` table, etc.) is unaffected.

## Run

```bash
cd baileys-bridge
cp .env.example .env
# Set BRIDGE_TOKEN + LARAVEL_WEBHOOK_SECRET to match Laravel's
#   BAILEYS_BRIDGE_TOKEN and BAILEYS_WEBHOOK_SECRET.
npm install
npm run dev    # or: npm start
```

The bridge listens on `PORT` (default 3025) and stores each session's auth
state under `sessions/<session_key>/`.

## Endpoints (all require `Authorization: Bearer $BRIDGE_TOKEN`)

| Method | Path                                    | Purpose                                  |
| ------ | --------------------------------------- | ---------------------------------------- |
| POST   | `/sessions`                             | Start (or resume) a session              |
| GET    | `/sessions/:key`                        | Status snapshot                          |
| GET    | `/sessions/:key/qr`                     | Latest QR data URL                       |
| DELETE | `/sessions/:key`                        | Logout + wipe credentials                |
| POST   | `/sessions/:key/messages/text`          | `{ to, text }`                           |
| POST   | `/sessions/:key/messages/media`         | `{ to, type, url, caption?, mimetype? }` |
| POST   | `/sessions/:key/status`                 | `{ type: text|image|video, ... }`        |
| GET    | `/sessions/:key/groups`                 | List joined groups                       |
| GET    | `/sessions/:key/channels`               | List subscribed newsletters (channels)   |

## Webhook events sent to Laravel

POST → `LARAVEL_WEBHOOK_URL` with header `X-Baileys-Signature: <HMAC-SHA256 of raw body>`:

```json
{ "event": "session.qr",          "data": { "session_key": "...", "qr": "data:image/png;base64,...", "expires_at": "..." } }
{ "event": "session.connected",   "data": { "session_key": "...", "jid": "...@s.whatsapp.net", "phone_number": "...", "display_name": "..." } }
{ "event": "session.disconnected","data": { "session_key": "...", "reason": "..." } }
{ "event": "message.received",    "data": { "session_key": "...", "wa_message_id": "...", "chat_jid": "...", "sender_jid": "...", "type": "text|image|video|...", "content": "...", "media_url": null, "media_mime": null, "media_filename": null, "payload": { /* raw */ }, "timestamp": "..." } }
{ "event": "message.status",      "data": { "session_key": "...", "wa_message_id": "...", "status": "delivered|read|failed", "error_message": "..." } }
```
