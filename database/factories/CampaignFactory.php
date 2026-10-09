<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\MarketingChannel;
use App\Models\Campaign;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 week');
        $end = (clone $start)->modify('+30 days');

        return [
            'uuid' => (string) Str::uuid(),
            'shop_id' => Shop::factory(),
            'name' => fake()->catchPhrase(),
            'code' => 'CMP-' . strtoupper(Str::random(8)),
            'description' => fake()->optional()->sentence(),
            'campaign_type' => fake()->randomElement(CampaignType::cases()),
            'channel' => MarketingChannel::SOCIAL_MEDIA,
            'budget' => fake()->randomFloat(2, 1000, 50000),
            'spent' => 0,
            'currency' => 'KES',
            'start_date' => $start,
            'end_date' => $end,
            'target_revenue' => fake()->randomFloat(2, 5000, 100000),
            'target_conversions' => fake()->numberBetween(10, 500),
            'target_reach' => fake()->numberBetween(1000, 100000),
            'actual_revenue' => 0,
            'conversions' => 0,
            'impressions' => 0,
            'clicks' => 0,
            'reach' => 0,
            'status' => CampaignStatus::DRAFT,
            'auto_post_enabled' => false,
            'ai_assist_enabled' => false,
            'ai_settings' => null,
            'content_template' => null,
            'default_landing_url' => fake()->optional()->url(),
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn() => ['status' => CampaignStatus::ACTIVE]);
    }

    public function withAi(): static
    {
        return $this->state(fn() => [
            'ai_assist_enabled' => true,
            'ai_settings' => ['provider' => 'qwen', 'tone' => 'enthusiastic'],
        ]);
    }
}
