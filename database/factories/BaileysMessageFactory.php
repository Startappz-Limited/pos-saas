<?php

namespace Database\Factories;

use App\Enums\BaileysMessageDirection;
use App\Enums\BaileysMessageStatus;
use App\Enums\BaileysMessageType;
use App\Models\BaileysChat;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BaileysMessage>
 */
class BaileysMessageFactory extends Factory
{
    protected $model = BaileysMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jid = fake()->numerify('2547########') . '@s.whatsapp.net';

        return [
            'uuid' => (string) Str::uuid(),
            'baileys_session_id' => BaileysSession::factory(),
            'shop_id' => Shop::factory(),
            'baileys_chat_id' => null,
            'chat_jid' => $jid,
            'sender_jid' => $jid,
            'direction' => BaileysMessageDirection::Outbound,
            'type' => BaileysMessageType::Text,
            'status' => BaileysMessageStatus::Pending,
            'content' => fake()->sentence(),
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn() => [
            'direction' => BaileysMessageDirection::Inbound,
            'status' => BaileysMessageStatus::Delivered,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn() => [
            'status' => BaileysMessageStatus::Sent,
            'wa_message_id' => 'BAE' . strtoupper(Str::random(16)),
            'sent_at' => now(),
        ]);
    }

    public function failed(string $error = 'Bridge unreachable'): static
    {
        return $this->state(fn() => [
            'status' => BaileysMessageStatus::Failed,
            'error_message' => $error,
            'failed_at' => now(),
        ]);
    }

    public function forChat(BaileysChat $chat): static
    {
        return $this->state(fn() => [
            'baileys_session_id' => $chat->baileys_session_id,
            'shop_id' => $chat->shop_id,
            'baileys_chat_id' => $chat->id,
            'chat_jid' => $chat->jid,
        ]);
    }
}
