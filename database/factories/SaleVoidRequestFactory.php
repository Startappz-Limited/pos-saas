<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleVoidRequest>
 */
class SaleVoidRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'shop_id' => fn (array $attributes) => Sale::query()->find($attributes['sale_id'])?->shop_id,
            'requested_by' => User::factory(),
            'reason' => 'Keyed in twice',
            'status' => 'pending',
            'requested_at' => now(),
            'expires_at' => now()->addHours(SaleVoidRequest::EXPIRES_AFTER_HOURS),
        ];
    }
}
