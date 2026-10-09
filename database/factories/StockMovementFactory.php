<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $movementType = fake()->randomElement(StockMovementType::cases());
        $quantityBefore = fake()->numberBetween(50, 200);
        $quantity = fake()->numberBetween(5, 50);

        $quantityAfter = $movementType->isAddition()
            ? $quantityBefore + $quantity
            : max(0, $quantityBefore - $quantity);

        return [
            'shop_id' => Shop::factory(),
            'product_id' => Product::factory(),
            'variation_id' => null,
            'stock_batch_id' => null,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'unit_cost' => fake()->randomFloat(2, 10, 200),
            'reference_type' => null,
            'reference_uuid' => null,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => fn (array $attributes) => $attributes['created_by'],
        ];
    }

    public function intake(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => StockMovementType::INTAKE,
        ]);
    }

    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => StockMovementType::SALE,
        ]);
    }

    public function return(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => StockMovementType::RETURN,
        ]);
    }

    public function adjustmentAdd(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => StockMovementType::ADJUSTMENT_ADD,
        ]);
    }

    public function adjustmentRemove(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => StockMovementType::ADJUSTMENT_REMOVE,
        ]);
    }

    public function damage(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => StockMovementType::DAMAGE,
            'notes' => 'Damaged during handling',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => StockMovementType::EXPIRED,
            'notes' => 'Product expired',
        ]);
    }

    public function transferIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => StockMovementType::TRANSFER_IN,
        ]);
    }

    public function transferOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_type' => StockMovementType::TRANSFER_OUT,
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
}
