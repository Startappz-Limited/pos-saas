<?php

namespace Database\Factories;

use App\Models\BaileysContact;
use App\Models\BaileysSession;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaileysContact>
 */
class BaileysContactFactory extends Factory
{
    protected $model = BaileysContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = fake()->numerify('2547########');

        return [
            'baileys_session_id' => BaileysSession::factory(),
            'shop_id' => Shop::factory(),
            'jid' => $phone . '@s.whatsapp.net',
            'phone' => '+' . $phone,
            'name' => fake()->name(),
            'push_name' => fake()->firstName(),
        ];
    }
}
