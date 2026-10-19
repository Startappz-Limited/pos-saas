<?php

namespace Database\Factories;

use App\Models\BusinessInvite;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BusinessInvite>
 */
class BusinessInviteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role_id' => fn () => Role::query()->where('name', 'cashier')->value('id')
                ?? Role::query()->create(['name' => 'cashier', 'guard_name' => 'web'])->id,
            'shop_ids' => [],
            'token_hash' => BusinessInvite::hashToken(Str::random(40)),
            'expires_at' => now()->addDays(BusinessInvite::EXPIRES_AFTER_DAYS),
        ];
    }
}
