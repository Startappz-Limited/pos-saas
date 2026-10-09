<?php

namespace Database\Factories;

use App\Enums\BaileysChatType;
use App\Models\BaileysChat;
use App\Models\BaileysSession;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaileysChat>
 */
class BaileysChatFactory extends Factory
{
    protected $model = BaileysChat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jid = fake()->numerify('2547########') . '@s.whatsapp.net';

        return [
            'baileys_session_id' => BaileysSession::factory(),
            'shop_id' => Shop::factory(),
            'jid' => $jid,
            'name' => fake()->name(),
            'type' => BaileysChatType::Private,
            'unread_count' => 0,
        ];
    }

    public function group(): static
    {
        return $this->state(fn() => [
            'jid' => fake()->numerify('############') . '-' . fake()->numerify('##########') . '@g.us',
            'type' => BaileysChatType::Group,
            'name' => fake()->company() . ' Group',
        ]);
    }

    public function channel(): static
    {
        return $this->state(fn() => [
            'jid' => fake()->numerify('############') . '@newsletter',
            'type' => BaileysChatType::Channel,
            'name' => fake()->company() . ' Channel',
        ]);
    }
}
