<?php

namespace Database\Factories;

use App\Models\AbandonedCart;
use App\Models\AbandonedCartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbandonedCartItem>
 */
class AbandonedCartItemFactory extends Factory
{
    protected $model = AbandonedCartItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 3);
        $price = fake()->randomFloat(2, 100, 2000);

        return [
            'abandoned_cart_id' => AbandonedCart::factory(),
            'platform_product_id' => (string) fake()->numberBetween(1, 9999),
            'platform_variation_id' => null,
            'product_id' => null,
            'sku' => null,
            'name' => ucwords(fake()->words(2, true)),
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => round($price * $quantity, 2),
        ];
    }
}
