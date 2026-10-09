<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Enums\SocialPlatform;
use App\Models\Campaign;
use App\Models\CampaignPost;
use App\Models\Shop;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CampaignPost>
 */
class CampaignPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'campaign_id' => Campaign::factory(),
            'shop_id' => Shop::factory(),
            'social_account_id' => SocialAccount::factory(),
            'product_id' => null,
            'platform' => SocialPlatform::FACEBOOK,
            'status' => PostStatus::DRAFT,
            'caption' => fake()->paragraph(),
            'hashtags' => ['#fitness', '#wellness'],
            'call_to_action' => 'Shop now',
            'media_urls' => [fake()->imageUrl(800, 800)],
            'landing_url' => fake()->url(),
            'ai_generated' => false,
            'attempts' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function scheduled(?\DateTimeInterface $when = null): static
    {
        return $this->state(fn() => [
            'status' => PostStatus::SCHEDULED,
            'scheduled_at' => $when ?? now()->addHour(),
        ]);
    }

    public function published(): static
    {
        return $this->state(fn() => [
            'status' => PostStatus::PUBLISHED,
            'published_at' => now(),
            'external_post_id' => (string) fake()->numberBetween(1_000_000, 9_999_999_999),
        ]);
    }

    public function aiGenerated(): static
    {
        return $this->state(fn() => [
            'ai_generated' => true,
            'ai_provider' => 'qwen',
            'ai_model' => 'qwen-plus',
        ]);
    }
}
