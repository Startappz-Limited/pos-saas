<?php

namespace Database\Factories;

use App\Enums\SocialPlatform;
use App\Models\Shop;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platform = fake()->randomElement(SocialPlatform::cases());

        return [
            'uuid' => (string) Str::uuid(),
            'shop_id' => Shop::factory(),
            'platform' => $platform,
            'account_name' => fake()->company(),
            'external_account_id' => (string) fake()->numberBetween(1_000_000, 9_999_999_999),
            'username' => fake()->userName(),
            'avatar_url' => fake()->optional()->imageUrl(64, 64),
            'credentials' => Crypt::encryptString(json_encode([
                'access_token' => 'fake_token_' . Str::random(20),
            ])),
            'metadata' => ['fake' => true],
            'is_active' => true,
            'connected_at' => now(),
            'token_expires_at' => now()->addDays(60),
            'connected_by' => User::factory(),
        ];
    }

    public function facebook(): static
    {
        return $this->state(fn() => ['platform' => SocialPlatform::FACEBOOK]);
    }

    public function instagram(): static
    {
        return $this->state(fn() => ['platform' => SocialPlatform::INSTAGRAM]);
    }

    public function metaAds(): static
    {
        return $this->state(fn() => ['platform' => SocialPlatform::META_ADS]);
    }

    public function googleAds(): static
    {
        return $this->state(fn() => ['platform' => SocialPlatform::GOOGLE_ADS]);
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['is_active' => false]);
    }
}
