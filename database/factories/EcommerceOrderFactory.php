<?php

namespace Database\Factories;

use App\Enums\EcommerceOrderStatus;
use App\Models\EcommerceOrder;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EcommerceOrder>
 */
class EcommerceOrderFactory extends Factory
{
    protected $model = EcommerceOrder::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 500, 10000);
        $shippingTotal = fake()->randomFloat(2, 0, 500);
        $taxTotal = round($subtotal * 0.16, 2);
        $total = $subtotal + $shippingTotal + $taxTotal;

        return [
            'uuid' => (string) Str::uuid(),
            'shop_id' => Shop::factory(),
            'platform' => fake()->randomElement(['woocommerce', 'shopify']),
            'platform_order_id' => (string) fake()->unique()->numberBetween(1000, 99999),
            'order_number' => '#' . fake()->unique()->numberBetween(1001, 99999),
            'status' => fake()->randomElement(EcommerceOrderStatus::cases()),
            'payment_method' => fake()->randomElement(['cod', 'mpesa', 'card', 'bank_transfer']),
            'payment_status' => fake()->randomElement(['paid', 'unpaid']),
            'currency' => 'KES',
            'subtotal' => $subtotal,
            'discount_total' => 0,
            'shipping_total' => $shippingTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'billing_address' => [
                'address_1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'country' => 'KE',
            ],
            'shipping_address' => [
                'address_1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'country' => 'KE',
            ],
            'platform_created_at' => fake()->dateTimeBetween('-30 days'),
            'last_synced_at' => now(),
        ];
    }

    public function woocommerce(): static
    {
        return $this->state(fn() => ['platform' => 'woocommerce']);
    }

    public function shopify(): static
    {
        return $this->state(fn() => ['platform' => 'shopify']);
    }

    public function pending(): static
    {
        return $this->state(fn() => [
            'status' => EcommerceOrderStatus::Pending,
            'payment_status' => 'unpaid',
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn() => [
            'status' => EcommerceOrderStatus::Processing,
            'payment_status' => 'paid',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn() => [
            'status' => EcommerceOrderStatus::Completed,
            'payment_status' => 'paid',
        ]);
    }

    public function cod(): static
    {
        return $this->state(fn() => [
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
        ]);
    }

    public function converted(): static
    {
        return $this->state(fn() => [
            'converted_at' => now(),
        ]);
    }
}
