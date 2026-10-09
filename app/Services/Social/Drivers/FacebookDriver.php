<?php

namespace App\Services\Social\Drivers;

use App\Models\CampaignPost;
use App\Models\SocialAccount;
use App\Services\Social\Contracts\SocialPoster;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookDriver implements SocialPoster
{
    public function platform(): string
    {
        return 'facebook';
    }

    public function publish(SocialAccount $account, CampaignPost $post): array
    {
        $token = $account->getCredential('access_token');
        $pageId = $account->external_account_id;

        if (empty($token) || empty($pageId)) {
            return [
                'success' => false,
                'message' => 'Facebook page is not connected. Add an access token in Settings → Social Accounts.',
            ];
        }

        // If running with a fake/test token, do not call the real API – simulate success.
        if (str_starts_with((string) $token, 'fake_')) {
            return [
                'success' => true,
                'external_post_id' => 'fb_test_' . uniqid(),
                'external_post_url' => "https://facebook.com/{$pageId}/posts/test",
                'message' => 'Posted (simulated – fake credentials).',
                'raw' => ['simulated' => true],
            ];
        }

        $version = (string) config('services.meta.graph_version', 'v20.0');
        $endpoint = "https://graph.facebook.com/{$version}/{$pageId}/feed";

        try {
            $payload = [
                'message' => $post->fullText(),
                'access_token' => $token,
            ];

            $firstMedia = collect($post->media_urls ?? [])->first();
            if ($firstMedia) {
                $endpoint = "https://graph.facebook.com/{$version}/{$pageId}/photos";
                $payload['url'] = $firstMedia;
                $payload['caption'] = $post->fullText();
                unset($payload['message']);
            } elseif ($post->landing_url) {
                $payload['link'] = $post->landing_url;
            }

            $response = Http::asForm()->timeout(20)->post($endpoint, $payload);

            if (! $response->successful()) {
                Log::warning('Facebook publish failed', [
                    'page_id' => $pageId,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Facebook API error: ' . ($response->json('error.message') ?? $response->status()),
                    'raw' => $response->json(),
                ];
            }

            $body = $response->json();
            $externalId = $body['post_id'] ?? $body['id'] ?? null;

            return [
                'success' => true,
                'external_post_id' => $externalId,
                'external_post_url' => $externalId ? "https://facebook.com/{$externalId}" : null,
                'message' => 'Published to Facebook.',
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Facebook publish exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Facebook publish failed: ' . $e->getMessage(),
            ];
        }
    }
}
