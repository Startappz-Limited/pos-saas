<?php

namespace Database\Factories;

use App\Models\DeliveryCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryCompany>
 */
class DeliveryCompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Couriers',
            'contact_person' => fake()->name(),
            'phone' => '07'.fake()->numerify('########'),
            'email' => fake()->optional()->companyEmail(),
            'address' => fake()->optional()->streetAddress(),
            'is_active' => true,
        ];
    }
}
