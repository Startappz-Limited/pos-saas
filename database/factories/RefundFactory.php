<?php

namespace Database\Factories;

use App\Enums\RefundMethod;
use App\Models\Customer;
use App\Models\Refund;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'return_id' => SaleReturn::factory(),
            'customer_id' => Customer::factory(),
            'shop_id' => Shop::factory(),
            'method' => fake()->randomElement(RefundMethod::cases()),
            'amount' => fake()->randomFloat(2, 10, 500),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'processing',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'completed',
            'processed_by' => User::factory(),
            'processed_at' => now(),
            'transaction_id' => fake()->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'failed',
            'processed_by' => User::factory(),
            'processed_at' => now(),
            'failure_reason' => fake()->sentence(),
        ]);
    }

    public function withMethod(RefundMethod $method): static
    {
        return $this->state(fn(array $attributes) => [
            'method' => $method,
        ]);
    }

    public function forShop(int $shopId): static
    {
        return $this->state(fn(array $attributes) => [
            'shop_id' => $shopId,
        ]);
    }
}
