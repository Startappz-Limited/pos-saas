<?php

namespace Database\Factories;

use App\Models\ProductUnit;
use App\Models\ProductVariation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductUnitFactory extends Factory
{
    protected $model = ProductUnit::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'variation_id' => ProductVariation::factory(),
            'name' => fake()->unique()->word(),
            'factor' => 1,
            'precision' => 0,
            'barcode' => null,
            'selling_price' => fake()->randomFloat(2, 10, 100),
            'wholesale_price' => null,
            'is_base' => false,
            'can_sell' => true,
            'can_buy' => true,
            'is_default_purchase' => false,
            'sort_order' => 0,
        ];
    }

    public function base(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'piece',
            'factor' => 1,
            'is_base' => true,
            'is_default_purchase' => true,
        ]);
    }
}
