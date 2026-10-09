<?php

namespace App\Services\Social\Drivers;

use App\Models\CampaignPost;
use App\Models\SocialAccount;
use App\Services\Social\Contracts\SocialPoster;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramDriver implements SocialPoster
{
    public function platform(): string
    {
        return 'instagram';
    }

    /**
     * Instagram Graph publishing is a 2-step flow:
     *   1) POST /{ig-user-id}/media     – create a media container
     *   2) POST /{ig-user-id}/media_publish – publish the container
     */
    public function publish(SocialAccount $account, CampaignPost $post): array
    {
        $token = $account->getCredential('access_token');
        $igUserId = $account->external_account_id;

        if (empty($token) || empty($igUserId)) {
            return [
                'success' => false,
                'message' => 'Instagram Business account is not connected.',
            ];
        }

        $imageUrl = collect($post->media_urls ?? [])->first();
        if (empty($imageUrl)) {
            return [
                'success' => false,
                'message' => 'Instagram requires at least one image URL.',
            ];
        }

        if (str_starts_with((string) $token, 'fake_')) {
            return [
                'success' => true,
                'external_post_id' => 'ig_test_' . uniqid(),
                'external_post_url' => 'https://instagram.com/p/test',
                'message' => 'Posted (simulated – fake credentials).',
                'raw' => ['simulated' => true],
            ];
        }

        $version = (string) config('services.meta.graph_version', 'v20.0');
        $base = "https://graph.facebook.com/{$version}/{$igUserId}";

        try {
            $container = Http::asForm()->timeout(20)->post("{$base}/media", [
                'image_url' => $imageUrl,
                'caption' => $post->fullText(),
                'access_token' => $token,
            ]);

            if (! $container->successful()) {
                return [
                    'success' => false,
                    'message' => 'Instagram media container failed: ' . ($container->json('error.message') ?? $container->status()),
                    'raw' => $container->json(),
                ];
            }

            $creationId = $container->json('id');

            $publish = Http::asForm()->timeout(20)->post("{$base}/media_publish", [
                'creation_id' => $creationId,
                'access_token' => $token,
            ]);

            if (! $publish->successful()) {
                return [
                    'success' => false,
                    'message' => 'Instagram publish failed: ' . ($publish->json('error.message') ?? $publish->status()),
                    'raw' => $publish->json(),
                ];
            }

            $externalId = $publish->json('id');

            return [
                'success' => true,
                'external_post_id' => $externalId,
                'external_post_url' => $externalId ? "https://instagram.com/p/{$externalId}" : null,
                'message' => 'Published to Instagram.',
                'raw' => $publish->json(),
            ];
        } catch (\Throwable $e) {
            Log::error('Instagram publish exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Instagram publish failed: ' . $e->getMessage(),
            ];
        }
    }
}
