<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 5000);
        $totalCost = $subtotal * 0.6;
        $totalProfit = $subtotal - $totalCost;

        return [
            'uuid' => (string) Str::uuid(),
            'shop_id' => Shop::factory(),
            'register_id' => CashRegister::factory(),
            'customer_id' => null,
            'source_id' => null,
            'delivery_location' => fake()->address(),
            'walk_in_customer_name' => fake()->name(),
            'walk_in_customer_phone' => fake()->phoneNumber(),
            'invoice_number' => 'INV-' . strtoupper(Str::random(8)),
            'sale_type' => 'regular',
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'packaging_fee' => 0,
            'other_expenses' => 0,
            'total_amount' => $subtotal,
            'total_cost' => $totalCost,
            'total_profit' => $totalProfit,
            'paid_amount' => $subtotal,
            'balance_due' => 0,
            'payment_status' => 'paid',
            'payment_method' => 'cash',
            'is_cod' => false,
            'status' => 'completed',
            'completed_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
