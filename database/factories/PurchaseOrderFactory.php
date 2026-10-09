<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 1000, 50000);
        $taxRate = 0.15;
        $taxAmount = $subtotal * $taxRate;
        $shippingCost = fake()->randomFloat(2, 50, 500);
        $discountAmount = fake()->randomFloat(2, 0, 1000);
        $totalAmount = $subtotal + $taxAmount + $shippingCost - $discountAmount;

        $orderDate = fake()->dateTimeBetween('-6 months', 'now');
        $expectedDeliveryDate = fake()->dateTimeBetween($orderDate, '+30 days');

        return [
            'supplier_id' => Supplier::factory(),
            'shop_id' => Shop::factory(),
            'status' => PurchaseOrderStatus::DRAFT,
            'order_date' => $orderDate,
            'expected_delivery_date' => $expectedDeliveryDate,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'shipping_cost' => $shippingCost,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'currency' => 'USD',
            'payment_terms' => fake()->randomElement(['Net 30', 'Net 60', 'Net 90', 'Due on Receipt']),
            'payment_due_date' => fake()->dateTimeBetween($orderDate, '+90 days'),
            'payment_status' => 'unpaid',
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::DRAFT,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::PENDING,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::APPROVED,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function ordered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::ORDERED,
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(2),
        ]);
    }

    public function partiallyReceived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::PARTIALLY_RECEIVED,
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(10),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::RECEIVED,
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(15),
            'actual_delivery_date' => now()->subDays(2),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::CANCELLED,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseOrderStatus::ORDERED,
            'expected_delivery_date' => now()->subDays(5),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
            'payment_date' => now()->subDays(1),
        ]);
    }
}
