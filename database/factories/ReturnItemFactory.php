<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ReturnItem;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnItem>
 */
class ReturnItemFactory extends Factory
{
    protected $model = ReturnItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->randomFloat(2, 5, 100);

        return [
            'return_id' => SaleReturn::factory(),
            'sale_item_id' => SaleItem::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
            'condition' => fake()->randomElement(['unopened', 'opened', 'damaged', 'defective']),
            'condition_notes' => fake()->optional()->sentence(),
            'is_restockable' => fake()->boolean(70),
            'is_restocked' => false,
            'return_to_supplier' => false,
        ];
    }

    public function restockable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_restockable' => true,
            'condition' => 'unopened',
        ]);
    }

    public function restocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_restockable' => true,
            'is_restocked' => true,
            'restocked_at' => now(),
        ]);
    }

    public function damaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'condition' => 'damaged',
            'is_restockable' => false,
            'condition_notes' => fake()->sentence(),
        ]);
    }

    public function markedForSupplierReturn(): static
    {
        return $this->state(fn (array $attributes) => [
            'return_to_supplier' => true,
            'return_to_supplier_at' => now(),
            'return_to_supplier_notes' => fake()->sentence(),
        ]);
    }
}
