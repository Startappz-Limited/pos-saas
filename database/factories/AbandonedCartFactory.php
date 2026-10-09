<?php

namespace Database\Factories;

use App\Enums\AbandonedCartStatus;
use App\Models\AbandonedCart;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AbandonedCart>
 */
class AbandonedCartFactory extends Factory
{
    protected $model = AbandonedCart::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 500, 10000);

        return [
            'uuid' => (string) Str::uuid(),
            'shop_id' => Shop::factory(),
            'platform' => 'woocommerce',
            'platform_cart_id' => (string) fake()->unique()->numberBetween(1, 999999),
            'status' => AbandonedCartStatus::Abandoned,
            'platform_status' => 'abandoned',
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '07'.fake()->numerify('########'),
            'user_type' => 'GUEST',
            'capture_source' => 'checkout',
            'currency' => 'KES',
            'subtotal' => $subtotal,
            'tax_total' => 0,
            'total' => $subtotal,
            'checkout_link' => 'https://shop.example/checkout/?acr_token='.Str::random(32),
            'abandoned_at' => now()->subHour(),
            'reminders_sent' => 0,
            'last_activity' => 'abandoned',
        ];
    }

    public function status(AbandonedCartStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
