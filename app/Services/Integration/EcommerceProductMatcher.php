<?php

namespace App\Services\Integration;

use App\Models\Product;
use App\Models\ProductEcommerceSync;
use App\Models\ProductVariation;
use App\Models\Shop;

/**
 * Maps a product on a website (WooCommerce/Shopify) to the POS catalogue.
 *
 * Order sync and abandoned-cart sync both need this and must agree, so it lives
 * here instead of inside one of them. The lookup order is the one order sync has
 * always used: the product's sync record first (an explicit link staff made or
 * the product import created), then the SKU.
 */
class EcommerceProductMatcher
{
    /**
     * The local product id for a platform product, or null when nothing matches.
     *
     * The SKU fallback is deliberately NOT shop-scoped: SKUs are unique across
     * the whole catalogue, and this is exactly the lookup order sync has always
     * performed.
     */
    public function matchProductId(Shop $shop, string $platform, ?string $platformProductId, ?string $sku): ?int
    {
        if ($platformProductId) {
            $productId = ProductEcommerceSync::where('shop_id', $shop->id)
                ->where('platform', $platform)
                ->where('platform_product_id', $platformProductId)
                ->value('product_id');

            if ($productId) {
                return (int) $productId;
            }
        }

        if ($sku) {
            return Product::where('sku', $sku)->first()?->id;
        }

        return null;
    }

    /**
     * Match a cart/order line to a product AND, where the SKU identifies one, a
     * variation. A WooCommerce variation's SKU is sent as the line SKU, so it
     * resolves to the POS variation that carries the same SKU.
     *
     * @return array{product_id: int|null, variation_id: int|null}
     */
    public function matchLine(Shop $shop, string $platform, ?string $platformProductId, ?string $sku): array
    {
        $sku = $sku !== null ? trim($sku) : null;
        $productId = $this->matchProductId($shop, $platform, $platformProductId, $sku);

        if ($productId !== null) {
            return [
                'product_id' => $productId,
                'variation_id' => $sku ? $this->variationFor($productId, $sku) : null,
            ];
        }

        if ($sku) {
            $variation = ProductVariation::where('sku', $sku)->first(['id', 'product_id']);

            if ($variation) {
                return ['product_id' => (int) $variation->product_id, 'variation_id' => (int) $variation->id];
            }
        }

        return ['product_id' => null, 'variation_id' => null];
    }

    private function variationFor(int $productId, string $sku): ?int
    {
        $id = ProductVariation::where('product_id', $productId)->where('sku', $sku)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
