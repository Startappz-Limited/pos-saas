<?php

namespace Database\Factories;

use App\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->company(),
            'code' => 'SUP-'.strtoupper(Str::random(8)),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->phoneNumber(),
            'website' => fake()->url(),
            'contact_person' => fake()->name(),
            'contact_email' => fake()->email(),
            'contact_phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => fake()->country(),
            'postal_code' => fake()->postcode(),
            'tax_number' => fake()->numerify('TAX-########'),
            'registration_number' => fake()->numerify('REG-########'),
            'currency' => fake()->randomElement(['USD', 'EUR', 'GBP', 'JPY']),
            'payment_terms_days' => fake()->randomElement([15, 30, 45, 60, 90]),
            'credit_limit' => fake()->randomFloat(2, 10000, 100000),
            'current_balance' => 0,
            'total_orders' => 0,
            'order_count' => 0,
            'average_rating' => null,
            'on_time_deliveries' => 0,
            'late_deliveries' => 0,
            'status' => SupplierStatus::ACTIVE,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierStatus::ACTIVE,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierStatus::INACTIVE,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierStatus::SUSPENDED,
        ]);
    }

    public function blacklisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierStatus::BLACKLISTED,
        ]);
    }

    public function withOrders(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_orders' => fake()->randomFloat(2, 50000, 500000),
            'order_count' => fake()->numberBetween(10, 100),
            'average_rating' => fake()->randomFloat(2, 3.5, 5.0),
        ]);
    }

    public function withGoodDeliveryRecord(): static
    {
        $onTime = fake()->numberBetween(80, 100);
        $late = fake()->numberBetween(0, 10);

        return $this->state(fn (array $attributes) => [
            'on_time_deliveries' => $onTime,
            'late_deliveries' => $late,
            'average_rating' => fake()->randomFloat(2, 4.0, 5.0),
        ]);
    }

    public function withPoorDeliveryRecord(): static
    {
        $onTime = fake()->numberBetween(10, 40);
        $late = fake()->numberBetween(20, 50);

        return $this->state(fn (array $attributes) => [
            'on_time_deliveries' => $onTime,
            'late_deliveries' => $late,
            'average_rating' => fake()->randomFloat(2, 1.0, 3.0),
        ]);
    }

    public function nearCreditLimit(): static
    {
        $creditLimit = fake()->randomFloat(2, 50000, 100000);
        $currentBalance = $creditLimit * fake()->randomFloat(2, 0.90, 0.99);

        return $this->state(fn (array $attributes) => [
            'credit_limit' => $creditLimit,
            'current_balance' => $currentBalance,
        ]);
    }

    public function reachedCreditLimit(): static
    {
        $creditLimit = fake()->randomFloat(2, 50000, 100000);

        return $this->state(fn (array $attributes) => [
            'credit_limit' => $creditLimit,
            'current_balance' => $creditLimit,
        ]);
    }
}
