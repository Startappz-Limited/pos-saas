<?php

namespace Database\Factories;

use App\Enums\CreditAccountStatus;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditAccount>
 */
class CreditAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $creditLimit = fake()->randomFloat(2, 1000, 50000);
        $currentBalance = fake()->randomFloat(2, 0, $creditLimit);

        return [
            'uuid' => (string) Str::uuid(),
            'customer_id' => Customer::factory(),
            'shop_id' => Shop::factory(),
            'credit_limit' => $creditLimit,
            'current_balance' => $currentBalance,
            'available_credit' => max(0, $creditLimit - $currentBalance),
            'status' => CreditAccountStatus::ACTIVE,
            'is_suspended' => false,
            'suspension_reason' => null,
            'payment_terms_days' => 30,
            'grace_period_days' => 7,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => CreditAccountStatus::ACTIVE,
            'is_suspended' => false,
            'suspension_reason' => null,
        ]);
    }

    public function suspended(string $reason = 'Past due balance'): static
    {
        return $this->state(fn () => [
            'status' => CreditAccountStatus::SUSPENDED,
            'is_suspended' => true,
            'suspension_reason' => $reason,
        ]);
    }

    public function withBalance(float $balance = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'current_balance' => $balance,
            'available_credit' => max(0, (float) ($attributes['credit_limit'] ?? 0) - $balance),
        ]);
    }
}
