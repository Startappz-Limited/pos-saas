<?php

namespace Database\Factories;

use App\Enums\CreditTransactionType;
use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(CreditTransactionType::cases());
        $amount = fake()->randomFloat(2, 100, 5000);
        $balanceBefore = fake()->randomFloat(2, 0, 10000);
        $isDebit = $type->isDebit();

        return [
            'uuid' => (string) Str::uuid(),
            'credit_account_id' => CreditAccount::factory(),
            'customer_id' => Customer::factory(),
            'shop_id' => Shop::factory(),
            'transaction_number' => 'CTX-'.now()->format('ymd').'-'.fake()->unique()->numerify('#####'),
            'type' => $type,
            'debit' => $isDebit ? $amount : 0,
            'credit' => $isDebit ? 0 : $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $isDebit ? $balanceBefore + $amount : max(0, $balanceBefore - $amount),
            'due_date' => $isDebit ? now()->addDays(30)->toDateString() : null,
            'is_overdue' => false,
            'days_overdue' => 0,
            'description' => fake()->sentence(),
            'notes' => null,
        ];
    }

    public function forAccount(CreditAccount $creditAccount): static
    {
        return $this->state(fn () => [
            'credit_account_id' => $creditAccount->id,
            'customer_id' => $creditAccount->customer_id,
            'shop_id' => $creditAccount->shop_id,
        ]);
    }

    public function purchase(float $amount = 1000): static
    {
        return $this->state(fn () => [
            'type' => CreditTransactionType::PURCHASE,
            'debit' => $amount,
            'credit' => 0,
        ]);
    }

    public function payment(float $amount = 1000): static
    {
        return $this->state(fn () => [
            'type' => CreditTransactionType::PAYMENT,
            'debit' => 0,
            'credit' => $amount,
            'due_date' => null,
        ]);
    }
}
