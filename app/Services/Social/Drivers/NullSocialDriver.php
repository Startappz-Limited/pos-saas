<?php

namespace App\Services\Social\Drivers;

use App\Models\CampaignPost;
use App\Models\SocialAccount;
use App\Services\Social\Contracts\SocialPoster;

class NullSocialDriver implements SocialPoster
{
    public function platform(): string
    {
        return 'null';
    }

    public function publish(SocialAccount $account, CampaignPost $post): array
    {
        return [
            'success' => true,
            'external_post_id' => 'null_' . uniqid(),
            'external_post_url' => null,
            'message' => 'No-op driver: post recorded locally only.',
            'raw' => ['noop' => true],
        ];
    }
}
