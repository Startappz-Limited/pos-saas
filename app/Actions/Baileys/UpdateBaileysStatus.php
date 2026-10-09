<?php

namespace App\Actions\Baileys;

use App\Enums\BaileysChatType;
use App\Enums\BaileysMessageDirection;
use App\Enums\BaileysMessageStatus;
use App\Enums\BaileysMessageType;
use App\Enums\BaileysSessionStatus;
use App\Models\BaileysChat;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Support\BaileysJid;
use Startappz\WaGateway\Exceptions\GatewayException;
use Startappz\WaGateway\WaGateway;

/**
 * Post a status update (text or media) to the connected number's WhatsApp Status feed.
 *
 * Status broadcasts are stored against the synthetic chat JID `status@broadcast`.
 *
 * A status is encrypted separately for every recipient, so only the numbers it
 * is sent to can ever see it -- there is no "all my contacts". The old bridge
 * sent it to the shop's own number alone, so no customer ever saw one; it goes
 * to the shop's most recent WhatsApp contacts instead ({@see audience()}).
 */
class UpdateBaileysStatus
{
    /** The gateway's own rule for a person JID; anything else would fail the whole post. */
    private const PERSON_JID = '/^[1-9]\d{7,14}@s\.whatsapp\.net$/';

    public function __construct(protected WaGateway $gateway) {}

    /**
     * @param  array<string, mixed>  $payload  e.g. ['type' => 'text', 'text' => '...', 'background_color' => '#000000']
     *                                         or ['type' => 'image', 'url' => '...', 'text' => 'caption']
     */
    public function execute(BaileysSession $session, array $payload, ?int $sentBy = null): BaileysMessage
    {
        if ($session->status !== BaileysSessionStatus::Connected) {
            throw new \RuntimeException('Baileys session is not connected.');
        }

        $type = match ($payload['type'] ?? 'text') {
            'image' => BaileysMessageType::Image,
            'video' => BaileysMessageType::Video,
            default => BaileysMessageType::Text,
        };

        // The composer has a single text box: the status text, or the media's caption.
        $text = $payload['text'] ?? null;
        $caption = $payload['caption'] ?? $text;
        $recipients = $this->audience($session);

        $message = BaileysMessage::create([
            'baileys_session_id' => $session->id,
            'shop_id' => $session->shop_id,
            'sent_by' => $sentBy,
            'chat_jid' => 'status@broadcast',
            'sender_jid' => $session->jid,
            'direction' => BaileysMessageDirection::Outbound,
            'type' => $type,
            'status' => BaileysMessageStatus::Pending,
            'content' => $type === BaileysMessageType::Text ? $text : $caption,
            'media_url' => $payload['url'] ?? null,
            'payload' => $payload + ['recipient_count' => count($recipients)],
        ]);

        if ($recipients === []) {
            $message->markAsFailed('No one to show it to yet: a status goes to the people this number has chatted with.');

            return $message->fresh();
        }

        try {
            $result = $type === BaileysMessageType::Text
                ? $this->gateway->postTextStatus(
                    $session->session_key,
                    (string) $text,
                    $recipients,
                    $payload['background_color'] ?? null,
                    $message->uuid,
                )
                : $this->gateway->postMediaStatus(
                    $session->session_key,
                    $type->value,
                    $recipients,
                    url: $payload['url'] ?? null,
                    caption: $caption,
                    idempotencyKey: $message->uuid,
                );
        } catch (GatewayException $e) {
            $message->markAsFailed($e->getMessage());

            return $message->fresh();
        }

        $message->markAsSent($result['wa_message_id'] ?? null);

        return $message->fresh();
    }

    /**
     * Who a status from this session reaches: its most recently active private
     * chats that have a phone number, up to `baileys.status_max_recipients`.
     * WhatsApp generally shows a status only to people who have the number
     * saved, and customers who chat with the shop are the likeliest to have.
     *
     * @return list<string> person JIDs
     */
    public function audience(BaileysSession $session): array
    {
        $limit = max(0, (int) config('baileys.status_max_recipients', 256));

        return BaileysChat::query()
            ->where('baileys_session_id', $session->id)
            ->where('type', BaileysChatType::Private)
            ->where(fn ($q) => $q->where('jid', 'like', '%@s.whatsapp.net')->orWhereNotNull('phone'))
            ->orderByDesc('last_message_at')
            ->limit($limit)
            ->get(['jid', 'phone'])
            ->map(fn (BaileysChat $chat): ?string => str_ends_with($chat->jid, '@s.whatsapp.net')
                ? $chat->jid
                : ($chat->phone ? BaileysJid::fromPhone($chat->phone) : null))
            ->filter(fn (?string $jid): bool => $jid !== null && preg_match(self::PERSON_JID, $jid) === 1)
            ->unique()
            ->values()
            ->all();
    }
}
