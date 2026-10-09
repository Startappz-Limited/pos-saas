<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attribute>
 */
class AttributeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Color', 'Size', 'Material', 'Brand', 'Weight', 'Memory', 'Storage']);

        $valuesByAttribute = [
            'Color' => ['Red', 'Blue', 'Green', 'Yellow', 'Black', 'White'],
            'Size' => ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
            'Material' => ['Cotton', 'Polyester', 'Leather', 'Denim', 'Linen'],
            'Brand' => ['Nike', 'Adidas', 'Puma', 'Reebok', 'Under Armour'],
            'Weight' => ['500g', '1kg', '2kg', '3kg', '5kg'],
            'Memory' => ['64GB', '128GB', '256GB', '512GB', '1TB'],
            'Storage' => ['128GB', '256GB', '512GB', '1TB', '2TB'],
        ];

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'description' => fake()->sentence(),
            'values' => $valuesByAttribute[$name] ?? ['Value 1', 'Value 2', 'Value 3'],
            'type' => fake()->randomElement(['dropdown', 'radio', 'checkbox', 'color', 'button']),
            'display_order' => fake()->numberBetween(0, 100),
            'is_required' => fake()->boolean(30),
            'is_visible' => fake()->boolean(90),
            'is_active' => fake()->boolean(80),
        ];
    }
}
