<?php

namespace App\Actions\Social;

use App\Enums\SocialPlatform;
use App\Models\Shop;
use App\Models\SocialAccount;

class ConnectSocialAccount
{
    /**
     * Persist a manually-supplied social account (or one returned from an OAuth
     * callback). Credentials are encrypted via SocialAccount::setCredentials().
     *
     * @param  array{
     *     account_name: string,
     *     external_account_id?: string|null,
     *     username?: string|null,
     *     avatar_url?: string|null,
     *     credentials?: array<string, mixed>,
     *     metadata?: array<string, mixed>,
     *     token_expires_at?: \DateTimeInterface|string|null,
     * }  $data
     */
    public function handle(Shop $shop, SocialPlatform $platform, array $data): SocialAccount
    {
        $account = SocialAccount::firstOrNew([
            'shop_id' => $shop->id,
            'platform' => $platform,
            'external_account_id' => $data['external_account_id'] ?? null,
        ]);

        $account->fill([
            'shop_id' => $shop->id,
            'platform' => $platform,
            'account_name' => $data['account_name'],
            'external_account_id' => $data['external_account_id'] ?? null,
            'username' => $data['username'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'token_expires_at' => $data['token_expires_at'] ?? null,
            'is_active' => true,
            'connected_at' => now(),
            'last_error' => null,
        ]);

        if (! empty($data['credentials'])) {
            $account->setCredentials($data['credentials']);
        }

        $account->save();

        return $account->fresh();
    }
}
