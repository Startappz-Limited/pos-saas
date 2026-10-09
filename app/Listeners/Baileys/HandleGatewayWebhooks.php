<?php

namespace App\Listeners\Baileys;

use App\Enums\BaileysChatType;
use App\Enums\BaileysMessageDirection;
use App\Enums\BaileysMessageStatus;
use App\Enums\BaileysMessageType;
use App\Enums\BaileysSessionStatus;
use App\Models\BaileysChat;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\Customer;
use App\Services\BaileysMediaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Startappz\WaGateway\Events\GatewayEvent;
use Startappz\WaGateway\Events\MessageReceived;
use Startappz\WaGateway\Events\MessageStatusUpdated;
use Startappz\WaGateway\Events\SessionConnected;
use Startappz\WaGateway\Events\SessionDisconnected;
use Startappz\WaGateway\Events\SessionQrUpdated;

/**
 * Applies Chatway Gateway's webhooks to this app's WhatsApp records.
 *
 * The wa-gateway-laravel package receives them at POST /wa-gateway/webhook,
 * verifies the timestamp-bound signature, drops redeliveries, and fires the
 * events handled here. This replaces the old bridge's WebhookController.
 *
 * Gateway delivery is at least once and unordered, and listeners here must
 * allow for both. An event for a session this app doesn't know is acknowledged
 * and ignored: refusing it would only turn it into a dead letter.
 */
class HandleGatewayWebhooks
{
    /** How far along a sent message is; receipts only ever move it forward. */
    private const PROGRESS = ['pending' => 0, 'failed' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];

    public function __construct(protected BaileysMediaService $media) {}

    public function handleQrUpdated(SessionQrUpdated $event): void
    {
        $session = $this->session($event);

        if (! $session || $session->isStaleGatewayEvent($event->occurredAt())) {
            return;
        }

        $expiresAt = isset($event->data['expires_at']) ? Carbon::parse($event->data['expires_at']) : now()->addMinute();

        // QRs rotate about every minute; an older one arriving late must not
        // replace the one on screen.
        if ($session->qr_expires_at && $expiresAt->lt($session->qr_expires_at)) {
            return;
        }

        $session->update([
            'status' => BaileysSessionStatus::QrReady,
            'qr_code' => $event->data['qr'] ?? null,
            'qr_expires_at' => $expiresAt,
        ]);
    }

    public function handleConnected(SessionConnected $event): void
    {
        $session = $this->session($event);

        if (! $session || $session->isStaleGatewayEvent($event->occurredAt())) {
            return;
        }

        $session->markConnected(
            jid: $event->data['jid'] ?? null,
            phoneNumber: $event->data['phone_number'] ?? null,
            displayName: $event->data['display_name'] ?? null,
            at: $event->occurredAt(),
        );
    }

    public function handleDisconnected(SessionDisconnected $event): void
    {
        $session = $this->session($event);

        if (! $session || $session->isStaleGatewayEvent($event->occurredAt())) {
            return;
        }

        if ($event->loggedOut()) {
            $session->markDisconnected('Logged out on the phone: scan a new QR to reconnect.', $event->occurredAt());

            return;
        }

        $session->markReconnecting($event->occurredAt());
    }

    public function handleMessageReceived(MessageReceived $event): void
    {
        $session = $this->session($event);
        $data = $event->data;
        $chatJid = (string) ($data['chat_jid'] ?? '');
        $waMessageId = $data['wa_message_id'] ?? null;

        if (! $session || $chatJid === '') {
            return;
        }

        // Delivered at least once: the same message can arrive again.
        if ($waMessageId && BaileysMessage::query()
            ->where('baileys_session_id', $session->id)
            ->where('wa_message_id', $waMessageId)
            ->exists()) {
            return;
        }

        // Sent from the phone itself: part of the conversation, not unread.
        $outbound = ($data['direction'] ?? 'inbound') === 'outbound';

        $chat = BaileysChat::firstOrCreate(
            ['baileys_session_id' => $session->id, 'jid' => $chatJid],
            [
                'shop_id' => $session->shop_id,
                'type' => BaileysChatType::fromJid($chatJid),
                'name' => $data['chat_name'] ?? ($outbound ? null : ($data['sender_name'] ?? null)),
            ],
        );

        $type = BaileysMessageType::tryFrom((string) ($data['type'] ?? 'text')) ?? BaileysMessageType::Text;
        $content = $data['content'] ?? null;
        $isPrivate = $chat->type === BaileysChatType::Private;

        // In a group the sender is a participant; in a private chat, the chat.
        $senderJid = $outbound ? $session->jid : ($data['participant_jid'] ?? $chatJid);
        $senderPhone = $data['sender_phone']
            ?? (str_contains((string) $senderJid, '@s.whatsapp.net') ? Str::before((string) $senderJid, '@') : null);
        $chatPhone = $data['chat_phone'] ?? ($isPrivate && ! $outbound ? $senderPhone : null);

        // Backfill phone/name on private chats once we know them. WhatsApp now
        // hands out opaque `@lid` JIDs for some contacts, so the phone column
        // is the only reliable way to render a real number in the UI.
        $chatUpdates = [];
        if ($isPrivate) {
            if (! $chat->phone && $chatPhone) {
                $chatUpdates['phone'] = preg_replace('/\D+/', '', (string) $chatPhone) ?: $chatPhone;
            }
            $name = $data['chat_name'] ?? ($outbound ? null : ($data['sender_name'] ?? null));
            if (! $chat->name && $name) {
                $chatUpdates['name'] = $name;
            }
        }

        // The customer is whoever this chat is with: the sender of an inbound
        // message, the recipient of one sent from the phone.
        $customerId = $this->resolveCustomerId($session->shop_id, $outbound ? $chatPhone : ($senderPhone ?? $chatPhone));

        $mediaUrl = null;
        $mediaMime = $data['media_mime'] ?? null;
        $mediaFilename = $data['media_filename'] ?? null;
        $mediaSize = null;
        // Set when we refuse bytes we were sent. The gateway sends none at all
        // for media over 5 MB, so such a message simply has no file.
        $mediaSkipped = null;

        // Persist media bytes shipped by the gateway (base64, up to 5 MB) so the
        // browser can render them via storage/. BaileysMediaService enforces the
        // size cap, chat-type allowlist and per-shop quota -- nothing the gateway
        // sends can fill the disk on its own.
        if (! empty($data['media_data']) && is_string($data['media_data'])) {
            $result = $this->media->storeInbound(
                session: $session,
                chatType: $chat->type,
                type: $type,
                base64: $data['media_data'],
                mime: $mediaMime,
                filename: $mediaFilename,
                waMessageId: $waMessageId,
            );

            if ($result['stored']) {
                $mediaUrl = $result['url'];
                $mediaSize = $result['size'];
            } else {
                $mediaSkipped = $result['reason'];
            }
        }

        // The message and its chat change together or not at all. The media
        // file above can't be rolled back: if these rows fail, the gateway's
        // retry writes it again and `baileys:prune-media --orphans` removes
        // the stray copy.
        DB::transaction(function () use ($session, $chat, $chatUpdates, $customerId, $chatJid, $senderJid, $waMessageId, $outbound, $type, $content, $mediaUrl, $mediaMime, $mediaFilename, $mediaSize, $mediaSkipped, $data): void {
            BaileysMessage::create([
                'baileys_session_id' => $session->id,
                'shop_id' => $session->shop_id,
                'baileys_chat_id' => $chat->id,
                'customer_id' => $customerId,
                'chat_jid' => $chatJid,
                'sender_jid' => $senderJid,
                'wa_message_id' => $waMessageId,
                'direction' => $outbound ? BaileysMessageDirection::Outbound : BaileysMessageDirection::Inbound,
                'type' => $type,
                'status' => $outbound ? BaileysMessageStatus::Sent : BaileysMessageStatus::Delivered,
                'content' => $content,
                'media_url' => $mediaUrl,
                'media_mime' => $mediaMime,
                'media_filename' => $mediaFilename,
                'media_size' => $mediaSize,
                'payload' => array_diff_key($data, ['media_data' => true]) + array_filter([
                    'media_skipped' => $mediaSkipped,
                ]),
                'sent_at' => $this->parseTimestamp($data['timestamp'] ?? null),
            ]);

            $chat->update($chatUpdates + [
                'last_message_at' => now(),
                'last_message_preview' => mb_substr((string) ($content ?? '['.$type->value.']'), 0, 200),
                // A chat created just above has no unread_count loaded (the column
                // default isn't read back), so count from zero rather than from null.
            ] + ($outbound ? [] : ['unread_count' => (int) $chat->unread_count + 1]));
        });
    }

    public function handleMessageStatus(MessageStatusUpdated $event): void
    {
        $session = $this->session($event);
        $waMessageId = (string) ($event->data['wa_message_id'] ?? '');

        if (! $session || $waMessageId === '') {
            return;
        }

        $message = BaileysMessage::query()
            ->where('baileys_session_id', $session->id)
            ->where('wa_message_id', $waMessageId)
            ->first();

        $status = BaileysMessageStatus::tryFrom((string) ($event->data['status'] ?? ''));

        // Receipts aren't ordered: a late "delivered" must not undo a "read".
        if (! $message || ! $status
            || self::PROGRESS[$status->value] <= (self::PROGRESS[$message->status->value] ?? 0)) {
            return;
        }

        match ($status) {
            BaileysMessageStatus::Delivered => $message->markAsDelivered(),
            BaileysMessageStatus::Read => $message->markAsRead(),
            default => null,
        };
    }

    protected function session(GatewayEvent $event): ?BaileysSession
    {
        $session = BaileysSession::query()->where('session_key', $event->sessionKey)->first();

        if (! $session) {
            Log::info('Chatway Gateway event for an unknown session ignored', [
                'session_key' => $event->sessionKey,
                'event' => $event::class,
            ]);
        }

        return $session;
    }

    protected function resolveCustomerId(int $shopId, ?string $phone): ?int
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        return Customer::query()
            ->where('shop_id', $shopId)
            ->whereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') LIKE ?", ['%'.$digits.'%'])
            ->value('id');
    }

    protected function parseTimestamp(mixed $timestamp): Carbon
    {
        if ($timestamp === null || $timestamp === '') {
            return now();
        }

        try {
            if (is_numeric($timestamp)) {
                return Carbon::createFromTimestamp((int) $timestamp);
            }

            return Carbon::parse((string) $timestamp);
        } catch (\Throwable) {
            return now();
        }
    }
}
