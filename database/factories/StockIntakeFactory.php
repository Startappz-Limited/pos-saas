<?php

namespace Database\Factories;

use App\Enums\StockIntakeStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockIntake>
 */
class StockIntakeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantityReceived = fake()->randomFloat(2, 10, 500);
        $rejectionRate = fake()->randomFloat(2, 0, 0.1);
        $quantityRejected = $quantityReceived * $rejectionRate;
        $quantityAccepted = $quantityReceived - $quantityRejected;

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'shop_id' => Shop::factory(),
            'supplier_id' => Supplier::factory(),
            'product_id' => Product::factory(),
            'status' => StockIntakeStatus::PENDING,
            'intake_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'quantity_received' => $quantityReceived,
            'quantity_accepted' => $quantityAccepted,
            'quantity_rejected' => $quantityRejected,
            'unit' => 'pcs',
            'quality_status' => fake()->randomElement(['excellent', 'good', 'acceptable']),
            'storage_location' => fake()->optional()->bothify('Warehouse-##'),
            'bin_location' => fake()->optional()->bothify('Bin-??-###'),
            'batch_number' => fake()->optional()->bothify('BATCH-########'),
            'received_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StockIntakeStatus::PENDING,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StockIntakeStatus::IN_PROGRESS,
            'received_at' => now()->subHours(2),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StockIntakeStatus::COMPLETED,
            'received_at' => now()->subDays(2),
            'completed_by' => User::factory(),
            'completed_at' => now()->subDays(1),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StockIntakeStatus::CANCELLED,
        ]);
    }

    public function withQualityIssues(): static
    {
        return $this->state(function (array $attributes) {
            $quantityReceived = $attributes['quantity_received'];
            $rejectionRate = fake()->randomFloat(2, 0.15, 0.35);
            $quantityRejected = $quantityReceived * $rejectionRate;
            $quantityAccepted = $quantityReceived - $quantityRejected;

            return [
                'quantity_rejected' => $quantityRejected,
                'quantity_accepted' => $quantityAccepted,
                'quality_status' => fake()->randomElement(['poor', 'rejected']),
                'quality_notes' => fake()->sentence(),
            ];
        });
    }

    public function excellentQuality(): static
    {
        return $this->state(function (array $attributes) {
            $quantityReceived = $attributes['quantity_received'];

            return [
                'quantity_rejected' => 0,
                'quantity_accepted' => $quantityReceived,
                'quality_status' => 'excellent',
            ];
        });
    }

    public function withExpiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => fake()->dateTimeBetween('+6 months', '+2 years'),
        ]);
    }
}
