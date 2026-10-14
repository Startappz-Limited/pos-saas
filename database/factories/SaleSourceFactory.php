<?php

namespace Database\Factories;

use App\Models\SaleSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleSource>
 */
class SaleSourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'icon' => 'solar:shop-2-broken',
            'color' => fake()->hexColor(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
