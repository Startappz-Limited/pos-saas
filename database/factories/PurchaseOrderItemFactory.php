<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory()->create();
        $quantityOrdered = fake()->randomFloat(2, 10, 1000);
        $unitCost = fake()->randomFloat(2, 5, 500);
        $taxRate = 15;
        $discountPercent = fake()->randomFloat(2, 0, 10);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => $product->id,
            'sku' => $product->sku,
            'product_name' => $product->name,
            'quantity_ordered' => $quantityOrdered,
            'quantity_received' => 0,
            'quantity_remaining' => $quantityOrdered,
            'unit' => 'pcs',
            'unit_cost' => $unitCost,
            'tax_rate' => $taxRate,
            'discount_percent' => $discountPercent,
        ];
    }

    public function partiallyReceived(): static
    {
        return $this->state(function (array $attributes) {
            $quantityOrdered = $attributes['quantity_ordered'];
            $quantityReceived = $quantityOrdered * fake()->randomFloat(2, 0.3, 0.7);

            return [
                'quantity_received' => $quantityReceived,
                'quantity_remaining' => $quantityOrdered - $quantityReceived,
            ];
        });
    }

    public function fullyReceived(): static
    {
        return $this->state(function (array $attributes) {
            $quantityOrdered = $attributes['quantity_ordered'];

            return [
                'quantity_received' => $quantityOrdered,
                'quantity_remaining' => 0,
            ];
        });
    }

    public function withDiscount(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percent' => fake()->randomFloat(2, 10, 25),
        ]);
    }
}
