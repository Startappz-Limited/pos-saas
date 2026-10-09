<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturnItem>
 */
class PurchaseReturnItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitCost = fake()->randomFloat(2, 5, 100);

        return [
            'purchase_return_id' => PurchaseReturn::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'line_total' => $quantity * $unitCost,
            'condition' => fake()->randomElement(['damaged', 'defective', 'expired', 'wrong_item']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
