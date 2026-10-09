<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventorySnapshot>
 */
class InventorySnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(0, 500);
        $costPrice = fake()->randomFloat(2, 10, 200);
        $sellingPrice = $costPrice * fake()->randomFloat(2, 1.2, 2.5);

        return [
            'shop_id' => Shop::factory(),
            'product_id' => Product::factory(),
            'variation_id' => null,
            'quantity_on_hand' => $quantity,
            'total_value' => $quantity * $costPrice,
            'retail_value' => $quantity * $sellingPrice,
            'snapshot_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'created_by' => User::factory(),
            'updated_by' => fn (array $attributes) => $attributes['created_by'],
        ];
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

    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'snapshot_date' => $date,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_on_hand' => fake()->numberBetween(0, 10),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_on_hand' => 0,
            'total_value' => 0,
            'retail_value' => 0,
        ]);
    }
}
