<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashRegister>
 */
class CashRegisterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = \App\Models\CashRegister::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'shop_id' => \App\Models\Shop::factory(),
            'user_id' => \App\Models\User::factory(),
            'register_number' => 'REG-'.strtoupper(\Illuminate\Support\Str::random(6)),
            'register_date' => today(),
            'opening_balance' => 0,
            'expense_opening_balance' => 0,
            'expense_balance_used' => 0,
            'status' => 'open',
            'opened_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'closing_balance' => 0,
            'closed_at' => now(),
        ]);
    }
}
