<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'name' => fake()->company(),
            'owner_id' => null,
        ];
    }

    /**
     * The business factory-made records join by default: the first one in the
     * database, created on demand. Tests and seeds are single-business unless
     * they pass a business_id, which is what the isolation tests do.
     */
    public static function defaultId(): int
    {
        return Business::query()->orderBy('id')->value('id')
            ?? Business::factory()->create()->id;
    }
}
