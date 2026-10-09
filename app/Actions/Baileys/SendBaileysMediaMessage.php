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
use Illuminate\Support\Facades\DB;
use Startappz\WaGateway\Exceptions\GatewayException;
use Startappz\WaGateway\WaGateway;

class SendBaileysMediaMessage
{
    public function __construct(protected WaGateway $gateway) {}

    /**
     * A gateway failure is recorded on the returned message (status failed,
     * with the gateway's reason) rather than thrown.
     *
     * @param  'image'|'video'|'audio'|'document'|'sticker'  $mediaType
     * @param  string  $url  kept on the message for the inbox and re-download; the
     *                       gateway fetches it only when no bytes are given
     * @param  array<string, mixed>  $extras  mimetype, filename, and data: the file's
     *                                        bytes, base64-encoded
     */
    public function execute(
        BaileysSession $session,
        string $toJid,
        string $mediaType,
        string $url,
        ?string $caption = null,
        array $extras = [],
        ?int $sentBy = null,
        ?int $customerId = null,
    ): BaileysMessage {
        if ($session->status !== BaileysSessionStatus::Connected) {
            throw new \RuntimeException('Baileys session is not connected.');
        }

        [$chat, $message] = DB::transaction(function () use ($session, $toJid, $mediaType, $url, $caption, $extras, $sentBy, $customerId) {
            $chat = BaileysChat::firstOrCreate(
                ['baileys_session_id' => $session->id, 'jid' => $toJid],
                ['shop_id' => $session->shop_id, 'type' => BaileysChatType::fromJid($toJid)],
            );

            $message = BaileysMessage::create([
                'baileys_session_id' => $session->id,
                'shop_id' => $session->shop_id,
                'baileys_chat_id' => $chat->id,
                'customer_id' => $customerId,
                'sent_by' => $sentBy,
                'chat_jid' => $toJid,
                'sender_jid' => $session->jid,
                'direction' => BaileysMessageDirection::Outbound,
                'type' => BaileysMessageType::from($mediaType),
                'status' => BaileysMessageStatus::Pending,
                'content' => $caption,
                'media_url' => $url,
                'media_mime' => $extras['mimetype'] ?? null,
                'media_filename' => $extras['filename'] ?? null,
            ]);

            return [$chat, $message];
        });

        // Bytes go inline whenever we have them, so the gateway never has to
        // reach back into this app for the file -- no DNS, TLS or firewall
        // dependency. The URL is the fallback.
        $contents = is_string($extras['data'] ?? null) ? base64_decode($extras['data'], true) : false;

        // Outside the transaction, keyed by the message's uuid: see SendBaileysMessage.
        try {
            $result = $this->gateway->sendMedia(
                $session->session_key,
                $toJid,
                $mediaType,
                url: $contents === false ? $url : null,
                contents: $contents === false ? null : $contents,
                mime: $extras['mimetype'] ?? null,
                filename: $extras['filename'] ?? null,
                caption: $caption,
                idempotencyKey: $message->uuid,
            );
        } catch (GatewayException $e) {
            $message->markAsFailed($e->getMessage());

            return $message->fresh();
        }

        $message->markAsSent($result['wa_message_id'] ?? null);
        $chat->update([
            'last_message_at' => now(),
            'last_message_preview' => $caption ?: '['.$mediaType.']',
        ]);

        return $message->fresh();
    }
}
