<?php

namespace App\Services\Mpesa;

use App\Models\BusinessIntegration;
use App\Models\Shop;

/**
 * A shop's own M-Pesa Till or Paybill and the account details behind it
 * (stage 3, open question 2), as the gateway needs them. Only a number the
 * M-Pesa API has accepted for this shop counts.
 */
final readonly class MpesaAccount
{
    /**
     * @param  'till'|'paybill'  $type
     * @param  array{consumer_key: string, consumer_secret: string, passkey?: string|null}  $credentials
     */
    public function __construct(
        public string $shortcode,
        public string $type,
        public array $credentials,
    ) {}

    public static function forShop(Shop $shop): ?self
    {
        if (empty($shop->mpesa_shortcode) || $shop->mpesa_verified_at === null) {
            return null;
        }

        $integration = BusinessIntegration::query()
            ->where('shop_id', $shop->id)->where('type', BusinessIntegration::TYPE_MPESA)
            ->where('status', BusinessIntegration::STATUS_ACTIVE)->first();

        return $integration === null ? null : new self((string) $shop->mpesa_shortcode, (string) $shop->mpesa_shortcode_type, (array) $integration->credentials);
    }
}
