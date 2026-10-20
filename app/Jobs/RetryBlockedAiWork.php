<?php

namespace App\Jobs;

use App\Actions\Campaign\GenerateCampaignContent;
use App\Exceptions\InsufficientAiCredit;
use App\Models\AiBlockedWork;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AiWorkReady;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Runs AI work that was waiting for credit, after a top-up (Phase 4, decision
 * 4.9). If the credit runs out again before it gets its turn, the work simply
 * keeps waiting for the next top-up. Runs for the business it was dispatched
 * for (stage 1c: the worker restores it).
 */
class RetryBlockedAiWork implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public int $workId) {}

    public function handle(GenerateCampaignContent $generate): void
    {
        $work = AiBlockedWork::query()->find($this->workId);

        if ($work === null || $work->status !== AiBlockedWork::WAITING) {
            return;
        }

        try {
            $result = match ($work->kind) {
                AiBlockedWork::CAMPAIGN_CONTENT => $this->campaignContent($work, $generate),
                default => throw new \RuntimeException("Unknown AI work kind [{$work->kind}]."),
            };
        } catch (InsufficientAiCredit) {
            return;
        } catch (Throwable $e) {
            $work->update(['status' => AiBlockedWork::FAILED, 'error' => mb_substr($e->getMessage(), 0, 250), 'completed_at' => now()]);

            return;
        }

        $work->update(['status' => AiBlockedWork::DONE, 'result' => $result, 'completed_at' => now()]);

        User::query()->whereKey($work->requested_by)->first()?->notify(new AiWorkReady($work));
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignContent(AiBlockedWork $work, GenerateCampaignContent $generate): array
    {
        $payload = $work->payload;
        $campaign = Campaign::query()->findOrFail($payload['campaign_id']);
        $product = isset($payload['product_id']) ? Product::query()->find($payload['product_id']) : null;

        return $generate->handle($campaign, $product, $payload['overrides'] ?? []);
    }
}
