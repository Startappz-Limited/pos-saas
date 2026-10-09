<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // uuid, expense_number and status come from the model's boot hooks;
        // the rest are NOT NULL with no default.
        return [
            'shop_id' => Shop::factory(),
            'category_id' => ExpenseCategory::factory(),
            'title' => fake()->sentence(3),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'expense_date' => now()->toDateString(),
        ];
    }
}
