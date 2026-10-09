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

class SendBaileysMessage
{
    public function __construct(protected WaGateway $gateway) {}

    /**
     * Send a text message via Baileys to any JID (private chat or group).
     *
     * A gateway failure is recorded on the returned message (status failed,
     * with the gateway's reason) rather than thrown.
     */
    public function execute(
        BaileysSession $session,
        string $toJid,
        string $text,
        ?int $sentBy = null,
        ?int $customerId = null,
    ): BaileysMessage {
        if ($session->status !== BaileysSessionStatus::Connected) {
            throw new \RuntimeException('Baileys session is not connected.');
        }

        [$chat, $message] = DB::transaction(function () use ($session, $toJid, $text, $sentBy, $customerId) {
            $chat = $this->ensureChat($session, $toJid);

            $message = BaileysMessage::create([
                'baileys_session_id' => $session->id,
                'shop_id' => $session->shop_id,
                'baileys_chat_id' => $chat->id,
                'customer_id' => $customerId,
                'sent_by' => $sentBy,
                'chat_jid' => $toJid,
                'sender_jid' => $session->jid,
                'direction' => BaileysMessageDirection::Outbound,
                'type' => BaileysMessageType::Text,
                'status' => BaileysMessageStatus::Pending,
                'content' => $text,
            ]);

            return [$chat, $message];
        });

        // Sent after the rows commit, never inside the transaction: the gateway
        // can wait up to 15s for a reconnecting session, and a rollback after
        // WhatsApp accepted the message would erase the record of a sent message.
        // The message's uuid is its Idempotency-Key, so a retried call is
        // answered from the gateway's record instead of sending twice.
        try {
            $result = $this->gateway->sendText($session->session_key, $toJid, $text, $message->uuid);
        } catch (GatewayException $e) {
            $message->markAsFailed($e->getMessage());

            return $message->fresh();
        }

        $message->markAsSent($result['wa_message_id'] ?? null);
        $chat->update([
            'last_message_at' => now(),
            'last_message_preview' => mb_substr($text, 0, 200),
        ]);

        return $message->fresh();
    }

    protected function ensureChat(BaileysSession $session, string $jid): BaileysChat
    {
        return BaileysChat::firstOrCreate(
            ['baileys_session_id' => $session->id, 'jid' => $jid],
            [
                'shop_id' => $session->shop_id,
                'type' => BaileysChatType::fromJid($jid),
            ],
        );
    }
}
