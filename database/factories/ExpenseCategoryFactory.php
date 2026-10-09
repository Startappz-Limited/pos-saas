<?php

namespace Database\Factories;

use App\Enums\ExpenseCategoryType;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // uuid, slug, code and status are filled by the model's boot hooks;
        // name and type are the only NOT NULL columns without a default.
        return [
            'name' => fake()->unique()->words(2, true),
            'type' => ExpenseCategoryType::OPERATIONAL,
        ];
    }
}
