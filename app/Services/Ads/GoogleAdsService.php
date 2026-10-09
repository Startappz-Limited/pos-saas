<?php

namespace App\Services\Ads;

use App\Models\Campaign;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Log;

/**
 * Scaffold for the Google Ads API.
 *
 * The Google Ads REST API requires a developer token, OAuth2 access token, and a
 * login_customer_id header in addition to per-call gRPC-style payloads. Rather
 * than re-implement the SDK here, this scaffold gracefully simulates the call
 * when credentials are absent / fake, and exposes a single high-level entry
 * point you can wire to the official `googleads/google-ads-php` SDK once
 * credentials are available.
 */
class GoogleAdsService
{
    /**
     * @param  array<string, mixed>  $options
     * @return array{success: bool, message: string, ids?: array<string, string>, raw?: mixed}
     */
    public function createSearchCampaign(SocialAccount $adAccount, Campaign $campaign, array $options): array
    {
        $token = $adAccount->getCredential('access_token');
        $customerId = $adAccount->external_account_id;
        $developerToken = (string) config('services.google_ads.developer_token');

        if (empty($token) || empty($customerId) || empty($developerToken)) {
            return [
                'success' => false,
                'message' => 'Google Ads is not fully configured. Add the developer token and connect an ad account.',
            ];
        }

        if (str_starts_with((string) $token, 'fake_')) {
            return [
                'success' => true,
                'message' => 'Google Ads campaign created (simulated – fake credentials).',
                'ids' => [
                    'campaign_id' => 'sim_gads_' . uniqid(),
                    'ad_group_id' => 'sim_grp_' . uniqid(),
                ],
                'raw' => ['simulated' => true],
            ];
        }

        // Real implementation lives in a dedicated SDK adapter; document the contract.
        Log::info('Google Ads create requested (no live SDK bound).', [
            'customer_id' => $customerId,
            'campaign' => $campaign->id,
            'options' => $options,
        ]);

        return [
            'success' => false,
            'message' => 'Live Google Ads SDK is not bound. Install googleads/google-ads-php and wire it to GoogleAdsService::createSearchCampaign().',
        ];
    }
}
