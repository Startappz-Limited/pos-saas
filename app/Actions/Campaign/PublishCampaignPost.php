<?php

namespace App\Actions\Campaign;

use App\Enums\PostStatus;
use App\Models\CampaignPost;
use App\Services\Social\SocialPosterManager;

class PublishCampaignPost
{
    public function __construct(protected SocialPosterManager $posters) {}

    /**
     * Push a campaign post to its social platform and update its status.
     *
     * @return array{success: bool, message: string, post: CampaignPost}
     */
    public function handle(CampaignPost $post): array
    {
        if (! $post->isPublishable()) {
            return [
                'success' => false,
                'message' => 'Post is not in a publishable state.',
                'post' => $post,
            ];
        }

        $account = $post->socialAccount;
        if (! $account || ! $account->is_active) {
            $post->update([
                'status' => PostStatus::FAILED,
                'last_error' => 'No active social account is linked to this post.',
                'attempts' => $post->attempts + 1,
            ]);

            return ['success' => false, 'message' => 'No active social account.', 'post' => $post->fresh()];
        }

        $post->update([
            'status' => PostStatus::PUBLISHING,
            'attempts' => $post->attempts + 1,
        ]);

        $driver = $this->posters->for($account->platform);
        $result = $driver->publish($account, $post);

        if ($result['success']) {
            $post->update([
                'status' => PostStatus::PUBLISHED,
                'published_at' => now(),
                'external_post_id' => $result['external_post_id'] ?? null,
                'external_post_url' => $result['external_post_url'] ?? null,
                'last_error' => null,
            ]);
        } else {
            $post->update([
                'status' => PostStatus::FAILED,
                'last_error' => $result['message'] ?? 'Unknown publish error',
            ]);
        }

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'post' => $post->fresh(),
        ];
    }
}
