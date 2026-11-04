<?php

namespace Database\Factories;

use App\Models\Register;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Register>
 */
class RegisterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => 'Till '.fake()->numberBetween(2, 99),
            // R and digits (stage 10, step 5d): `W` is the web series.
            'code' => 'R'.fake()->unique()->numberBetween(100, 999999),
            'last_invoice_number' => 0,
        ];
    }
}
