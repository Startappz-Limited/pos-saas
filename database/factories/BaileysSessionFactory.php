<?php

namespace Database\Factories;

use App\Enums\BaileysSessionStatus;
use App\Models\BaileysSession;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BaileysSession>
 */
class BaileysSessionFactory extends Factory
{
    protected $model = BaileysSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'shop_id' => Shop::factory(),
            'created_by' => User::factory(),
            'name' => 'Default',
            'session_key' => 'shop-test-' . Str::random(8),
            'status' => BaileysSessionStatus::Pending,
        ];
    }

    public function connected(): static
    {
        return $this->state(fn() => [
            'status' => BaileysSessionStatus::Connected,
            'jid' => fake()->numerify('2547########') . '@s.whatsapp.net',
            'phone_number' => '+' . fake()->numerify('2547########'),
            'display_name' => fake()->name(),
            'connected_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    public function qrReady(string $qr = 'data:image/png;base64,FAKE'): static
    {
        return $this->state(fn() => [
            'status' => BaileysSessionStatus::QrReady,
            'qr_code' => $qr,
            'qr_expires_at' => now()->addMinute(),
        ]);
    }
}
