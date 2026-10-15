<?php

namespace Database\Factories;

use App\Models\Register;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'code' => 'R'.strtoupper(Str::random(3)),
            'last_invoice_number' => 0,
        ];
    }
}
