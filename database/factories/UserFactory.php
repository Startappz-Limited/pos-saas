<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'date_of_birth' => fake()->date('Y-m-d', '-18 years'),
            'status' => UserStatus::ACTIVE,
        ];
    }

    /**
     * A user joins their default business when the factory was given one
     * (memberOf()), otherwise the business the code runs for (in tests, the
     * test's business), so their requests act on it.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $business = $user->default_business_id !== null
                ? Business::find($user->default_business_id)
                : app(TenantContext::class)->business();

            if ($business) {
                $user->joinBusiness($business);
            }
        });
    }

    /**
     * A member of $business only, with it as the default business.
     */
    public function memberOf(Business $business): static
    {
        return $this->state(['default_business_id' => $business->id]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::INACTIVE,
        ]);
    }

    /**
     * Indicate that the user is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::SUSPENDED,
        ]);
    }
}
