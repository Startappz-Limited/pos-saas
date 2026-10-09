<?php

namespace Database\Factories;

use App\Enums\PricingType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PricingRule>
 */
class PricingRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomFloat(2, 10, 500);

        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->words(3, true).' Pricing',
            'description' => fake()->optional()->sentence(),
            'type' => fake()->randomElement(PricingType::cases())->value,
            'product_id' => Product::factory(),
            'price' => $price,
            'discount_percentage' => null,
            'discount_amount' => null,
            'min_quantity' => null,
            'max_quantity' => null,
            'customer_id' => null,
            'start_date' => null,
            'end_date' => null,
            'priority' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function regular(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PricingType::REGULAR->value,
            'priority' => 0,
        ]);
    }

    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PricingType::MEMBER->value,
            'discount_percentage' => fake()->randomFloat(2, 5, 20),
            'priority' => 5,
        ]);
    }

    public function bulk(): static
    {
        $minQty = fake()->numberBetween(5, 20);

        return $this->state(fn (array $attributes) => [
            'type' => PricingType::BULK->value,
            'min_quantity' => $minQty,
            'max_quantity' => $minQty + fake()->numberBetween(30, 100),
            'discount_percentage' => fake()->randomFloat(2, 10, 30),
            'priority' => 7,
        ]);
    }

    public function promotional(): static
    {
        $startDate = now()->addDays(fake()->numberBetween(-30, 0));

        return $this->state(fn (array $attributes) => [
            'type' => PricingType::PROMOTIONAL->value,
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addDays(fake()->numberBetween(7, 30)),
            'discount_percentage' => fake()->randomFloat(2, 15, 50),
            'priority' => 8,
        ]);
    }

    public function customerSpecific(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PricingType::CUSTOMER_SPECIFIC->value,
            'customer_id' => User::factory(),
            'discount_percentage' => fake()->randomFloat(2, 5, 25),
            'priority' => 10,
        ]);
    }

    public function timeBased(): static
    {
        $startDate = now()->setTime(fake()->numberBetween(6, 12), 0);

        return $this->state(fn (array $attributes) => [
            'type' => PricingType::TIME_BASED->value,
            'start_date' => $startDate,
            'end_date' => $startDate->copy()->addHours(fake()->numberBetween(2, 6)),
            'discount_percentage' => fake()->randomFloat(2, 10, 30),
            'priority' => 6,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subDays(30),
            'end_date' => now()->subDays(1),
            'is_active' => true,
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->addDays(1),
            'end_date' => now()->addDays(30),
            'is_active' => true,
        ]);
    }
}
