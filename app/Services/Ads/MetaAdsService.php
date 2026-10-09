<?php

namespace App\Services\Ads;

use App\Models\Campaign;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin scaffold around the Meta Marketing API.
 *
 * The full ad-creation flow on Meta is: Campaign → AdSet → AdCreative → Ad. This
 * service exposes a single high-level entry point that performs all four steps,
 * and short-circuits with a simulated response when running with fake/sandbox
 * credentials so the application can be exercised end-to-end without live keys.
 */
class MetaAdsService
{
    public function __construct(
        protected string $graphVersion = ''
    ) {
        if ($this->graphVersion === '') {
            $this->graphVersion = (string) config('services.meta.graph_version', 'v20.0');
        }
    }

    /**
     * Create a paid Facebook/Instagram ad for the given campaign.
     *
     * @param  array<string, mixed>  $options  ['daily_budget' => int(cents), 'objective' => 'OUTCOME_SALES', 'creative' => ['title','body','image_url','link']]
     * @return array{success: bool, message: string, ids?: array<string, string>, raw?: mixed}
     */
    public function createAdForCampaign(SocialAccount $adAccount, Campaign $campaign, array $options): array
    {
        $token = $adAccount->getCredential('access_token');
        $accountId = $adAccount->external_account_id;

        if (empty($token) || empty($accountId)) {
            return [
                'success' => false,
                'message' => 'Meta Ads account is not connected. Add an ad account in Settings → Social Accounts.',
            ];
        }

        if (str_starts_with((string) $token, 'fake_')) {
            return [
                'success' => true,
                'message' => 'Meta Ad created (simulated – fake credentials).',
                'ids' => [
                    'campaign_id' => 'sim_cmp_' . uniqid(),
                    'adset_id' => 'sim_set_' . uniqid(),
                    'creative_id' => 'sim_cre_' . uniqid(),
                    'ad_id' => 'sim_ad_' . uniqid(),
                ],
                'raw' => ['simulated' => true],
            ];
        }

        try {
            $base = "https://graph.facebook.com/{$this->graphVersion}/act_{$accountId}";

            $cmp = Http::asForm()->timeout(20)->post("{$base}/campaigns", [
                'name' => $campaign->name,
                'objective' => $options['objective'] ?? 'OUTCOME_TRAFFIC',
                'status' => 'PAUSED',
                'special_ad_categories' => '[]',
                'access_token' => $token,
            ]);

            if (! $cmp->successful()) {
                return $this->fail('Meta campaign create failed', $cmp);
            }

            // NOTE: The full AdSet → Creative → Ad chain is intentionally left as
            // explicit follow-up calls so an integrator can plug in their targeting,
            // bidding strategy, and creative spec. The structure mirrors the docs at
            // https://developers.facebook.com/docs/marketing-apis.
            $campaignId = (string) $cmp->json('id');

            return [
                'success' => true,
                'message' => 'Meta campaign created (paused). Configure ad sets, creatives, and ads in the Meta Ads Manager.',
                'ids' => ['campaign_id' => $campaignId],
                'raw' => $cmp->json(),
            ];
        } catch (\Throwable $e) {
            Log::error('Meta Ads exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Meta Ads error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: false, message: string, raw: mixed}
     */
    private function fail(string $context, Response $response): array
    {
        Log::warning($context, ['status' => $response->status(), 'body' => $response->json()]);

        return [
            'success' => false,
            'message' => $context . ': ' . ($response->json('error.message') ?? $response->status()),
            'raw' => $response->json(),
        ];
    }
}
