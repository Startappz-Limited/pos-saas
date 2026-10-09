<?php

namespace Database\Factories;

use App\Enums\PurchaseReturnReason;
use App\Enums\PurchaseReturnStatus;
use App\Models\PurchaseReturn;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturn>
 */
class PurchaseReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'shop_id' => Shop::factory(),
            'status' => PurchaseReturnStatus::PENDING,
            'reason' => fake()->randomElement(PurchaseReturnReason::cases()),
            'notes' => fake()->optional()->sentence(),
            'total_amount' => fake()->randomFloat(2, 10, 500),
            'supplier_credit_amount' => 0,
            'requested_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseReturnStatus::APPROVED,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseReturnStatus::SHIPPED,
            'approved_by' => User::factory(),
            'approved_at' => now()->subHour(),
            'shipped_by' => User::factory(),
            'shipped_at' => now(),
        ]);
    }
}
