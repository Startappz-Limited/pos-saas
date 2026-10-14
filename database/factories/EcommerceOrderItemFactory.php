<?php

namespace Database\Factories;

use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EcommerceOrderItem>
 */
class EcommerceOrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 4);
        $unitPrice = fake()->randomFloat(2, 100, 5000);
        $subtotal = round($quantity * $unitPrice, 2);

        return [
            'ecommerce_order_id' => EcommerceOrder::factory(),
            'platform_product_id' => (string) fake()->numberBetween(1000, 99999),
            'platform_line_item_id' => (string) fake()->numberBetween(100000, 999999),
            'product_id' => null,
            'name' => fake()->words(3, true),
            'sku' => 'WEB-'.fake()->bothify('????-####'),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'tax_total' => 0,
            'platform_data' => null,
        ];
    }
}
