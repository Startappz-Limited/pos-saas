<?php

namespace Database\Factories;

use App\Enums\AlertStatus;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LowStockAlert>
 */
class LowStockAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $threshold = fake()->numberBetween(10, 50);
        $currentQuantity = fake()->numberBetween(0, $threshold);

        return [
            'shop_id' => Shop::factory(),
            'product_id' => Product::factory(),
            'variation_id' => null,
            'current_quantity' => $currentQuantity,
            'threshold_quantity' => $threshold,
            'status' => AlertStatus::PENDING,
            'acknowledged_at' => null,
            'acknowledged_by' => null,
            'created_by' => User::factory(),
            'updated_by' => fn (array $attributes) => $attributes['created_by'],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::PENDING,
            'acknowledged_at' => null,
            'acknowledged_by' => null,
        ]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::ACKNOWLEDGED,
            'acknowledged_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'acknowledged_by' => User::factory(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::RESOLVED,
            'acknowledged_at' => fake()->dateTimeBetween('-14 days', '-7 days'),
            'acknowledged_by' => User::factory(),
        ]);
    }

    public function ignored(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AlertStatus::IGNORED,
            'acknowledged_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'acknowledged_by' => User::factory(),
        ]);
    }

    public function withVariation(): static
    {
        return $this->state(fn (array $attributes) => [
            'variation_id' => ProductVariation::factory(),
        ]);
    }

    public function forShop(int $shopId): static
    {
        return $this->state(fn (array $attributes) => [
            'shop_id' => $shopId,
        ]);
    }

    public function forProduct(int $productId): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_quantity' => 0,
        ]);
    }

    public function critical(): static
    {
        return $this->state(function (array $attributes) {
            $threshold = $attributes['threshold_quantity'];

            return [
                'current_quantity' => fake()->numberBetween(0, (int) ($threshold * 0.2)),
            ];
        });
    }
}
