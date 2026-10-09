<?php

namespace Database\Factories;

use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' '.fake()->randomElement(['Shop', 'Store', 'Outlet', 'Branch']);

        return [
            'business_id' => fn (): int => BusinessFactory::defaultId(),
            'uuid' => Str::uuid(),
            'name' => $name,
            'code' => strtoupper(Str::slug($name, '-')),
            'description' => fake()->optional()->paragraph(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => 'Kenya',
            'postal_code' => fake()->postcode(),
            'latitude' => fake()->latitude(-4.7, 4.7), // Kenya coordinates
            'longitude' => fake()->longitude(33.9, 41.9), // Kenya coordinates
            'status' => ShopStatus::ACTIVE,
            'manager_id' => User::factory(),
            'settings' => [
                'currency' => 'KES',
                'timezone' => 'Africa/Nairobi',
                'tax_rate' => fake()->randomFloat(2, 0, 20),
                'opening_time' => '08:00',
                'closing_time' => '20:00',
            ],
        ];
    }

    /**
     * Indicate that the shop is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShopStatus::INACTIVE,
        ]);
    }

    /**
     * Indicate that the shop is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ShopStatus::SUSPENDED,
        ]);
    }

    /**
     * Indicate that the shop has no manager.
     */
    public function withoutManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'manager_id' => null,
        ]);
    }

    /**
     * Indicate that the shop has minimal settings.
     */
    public function minimalSettings(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => null,
        ]);
    }
}
