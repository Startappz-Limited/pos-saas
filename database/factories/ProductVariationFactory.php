<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductVariationFactory extends Factory
{
    protected $model = ProductVariation::class;

    public function definition(): array
    {
        $costPrice = fake()->randomFloat(2, 10, 100);
        $sellingPrice = $costPrice * fake()->randomFloat(2, 1.2, 2.0);

        return [
            'uuid' => (string) Str::uuid(),
            'product_id' => Product::factory(),
            'name' => fake()->words(2, true),
            'sku' => 'SKU-VAR-'.strtoupper(Str::random(8)),
            'barcode' => fake()->optional()->ean13(),
            'attributes' => [
                'size' => fake()->randomElement(['Small', 'Medium', 'Large']),
                'color' => fake()->safeColorName(),
            ],
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'stock_quantity' => fake()->numberBetween(0, 50),
            'image' => null,
            'status' => ProductStatus::ACTIVE,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::ACTIVE,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }
}
