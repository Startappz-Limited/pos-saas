<?php

namespace App\Services\Social\Contracts;

use App\Models\CampaignPost;
use App\Models\SocialAccount;

interface SocialPoster
{
    /**
     * Publish a campaign post on the platform represented by $account.
     *
     * @return array{
     *     success: bool,
     *     external_post_id?: string|null,
     *     external_post_url?: string|null,
     *     message: string,
     *     raw?: mixed,
     * }
     */
    public function publish(SocialAccount $account, CampaignPost $post): array;

    public function platform(): string;
}
