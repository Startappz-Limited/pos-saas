<?php

namespace Database\Factories;

use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleReturn>
 */
class ReturnFactory extends Factory
{
    protected $model = SaleReturn::class;

    public function definition(): array
    {
        $totalAmount = fake()->randomFloat(2, 10, 500);
        $restockingFee = fake()->randomFloat(2, 0, $totalAmount * 0.15);
        $refundAmount = $totalAmount - $restockingFee;

        return [
            'sale_id' => Sale::factory(),
            'customer_id' => Customer::factory(),
            'shop_id' => Shop::factory(),
            'status' => ReturnStatus::PENDING,
            'reason' => fake()->randomElement(ReturnReason::cases()),
            'notes' => fake()->optional()->sentence(),
            'total_amount' => $totalAmount,
            'restocking_fee' => $restockingFee,
            'refund_amount' => $refundAmount,
            'requested_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ReturnStatus::PENDING,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ReturnStatus::APPROVED,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ReturnStatus::REJECTED,
            'approved_by' => User::factory(),
            'approved_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ReturnStatus::RECEIVED,
            'approved_by' => User::factory(),
            'approved_at' => now()->subHour(),
            'received_at' => now(),
        ]);
    }

    public function inspected(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ReturnStatus::INSPECTED,
            'approved_by' => User::factory(),
            'approved_at' => now()->subHours(2),
            'received_at' => now()->subHour(),
            'inspected_at' => now(),
            'inspected_by' => User::factory(),
            'inspection_notes' => fake()->sentence(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => ReturnStatus::COMPLETED,
            'approved_by' => User::factory(),
            'approved_at' => now()->subHours(3),
            'received_at' => now()->subHours(2),
            'inspected_at' => now()->subHour(),
            'inspected_by' => User::factory(),
        ]);
    }

    public function forShop(int $shopId): static
    {
        return $this->state(fn(array $attributes) => [
            'shop_id' => $shopId,
        ]);
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(fn(array $attributes) => [
            'customer_id' => $customerId,
        ]);
    }

    public function withReason(ReturnReason $reason): static
    {
        return $this->state(fn(array $attributes) => [
            'reason' => $reason,
        ]);
    }
}
