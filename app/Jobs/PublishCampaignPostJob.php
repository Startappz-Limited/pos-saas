<?php

namespace App\Jobs;

use App\Actions\Campaign\PublishCampaignPost;
use App\Models\CampaignPost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PublishCampaignPostJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(public int $campaignPostId) {}

    public function handle(PublishCampaignPost $publish): void
    {
        $post = CampaignPost::find($this->campaignPostId);

        if (! $post) {
            Log::warning('Campaign post not found for publishing', ['id' => $this->campaignPostId]);

            return;
        }

        $publish->handle($post);
    }
}
