<?php

namespace App\Actions\Campaign;

use App\Enums\PostStatus;
use App\Jobs\PublishCampaignPostJob;
use App\Models\Campaign;
use App\Models\CampaignPost;
use App\Models\Product;
use App\Models\SocialAccount;
use Illuminate\Support\Carbon;

class ScheduleCampaignPost
{
    /**
     * Persist a draft / scheduled campaign post and queue the publish job when
     * a schedule is provided.
     *
     * @param  array{
     *     caption: string,
     *     hashtags?: array<int, string>,
     *     call_to_action?: string|null,
     *     media_urls?: array<int, string>,
     *     landing_url?: string|null,
     *     ai_generated?: bool,
     *     ai_provider?: string|null,
     *     ai_model?: string|null,
     *     ai_prompt_context?: array<string, mixed>|null,
     * }  $content
     */
    public function handle(
        Campaign $campaign,
        SocialAccount $account,
        array $content,
        ?Carbon $scheduledAt = null,
        ?Product $product = null,
    ): CampaignPost {
        $post = CampaignPost::create([
            'campaign_id' => $campaign->id,
            'shop_id' => $campaign->shop_id,
            'social_account_id' => $account->id,
            'product_id' => $product?->id,
            'platform' => $account->platform,
            'status' => $scheduledAt ? PostStatus::SCHEDULED : PostStatus::DRAFT,
            'caption' => $content['caption'],
            'hashtags' => $content['hashtags'] ?? [],
            'call_to_action' => $content['call_to_action'] ?? null,
            'media_urls' => $content['media_urls'] ?? [],
            'landing_url' => $content['landing_url'] ?? $campaign->default_landing_url,
            'ai_generated' => (bool) ($content['ai_generated'] ?? false),
            'ai_provider' => $content['ai_provider'] ?? null,
            'ai_model' => $content['ai_model'] ?? null,
            'ai_prompt_context' => $content['ai_prompt_context'] ?? null,
            'scheduled_at' => $scheduledAt,
        ]);

        if ($scheduledAt) {
            PublishCampaignPostJob::dispatch($post->id)->delay($scheduledAt);
        }

        return $post;
    }
}
