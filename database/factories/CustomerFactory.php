<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'shop_id' => Shop::factory(),
            'code' => 'CUS-'.strtoupper(Str::random(6)),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'customer_type' => fake()->randomElement(['retail', 'wholesale']),
            'allow_credit' => false,
            'credit_limit' => 0,
            'credit_balance' => 0,
            'status' => 'active',
            'notes' => null,
        ];
    }

    /**
     * Attach the customer to a specific shop.
     *
     * Without this, `Customer::factory()->forShop($shop)` falls through to the
     * factory's magic __call and the Shop is treated as a state array, blowing up
     * in array_merge. Mirrors WhatsAppMessageFactory::forShop().
     */
    public function forShop(Shop $shop): static
    {
        return $this->state(fn () => [
            'shop_id' => $shop->id,
        ]);
    }

    /**
     * A customer with no phone number — used to assert that WhatsApp sends fail
     * cleanly rather than dispatching to nothing.
     */
    public function withoutPhone(): static
    {
        return $this->state(fn () => [
            'phone' => null,
        ]);
    }

    public function wholesale(): static
    {
        return $this->state(fn () => [
            'customer_type' => 'wholesale',
        ]);
    }

    public function withCredit(float $limit = 50000): static
    {
        return $this->state(fn () => [
            'customer_type' => 'wholesale',
            'allow_credit' => true,
            'credit_limit' => $limit,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 'inactive',
        ]);
    }
}
