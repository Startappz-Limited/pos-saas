<?php

namespace App\Support\Stock;

use App\Casts\Quantity;
use App\Models\CostLayer;
use App\Models\Shop;
use Illuminate\Support\Collection;

/**
 * What a shop can sell of each variation, by batch (stage 11, step 8): the
 * sellable quantity (not expired), the expired quantity, and the next expiry
 * still ahead. One query per shop, so the pull reads all its variations without
 * a query each. Expiry is judged by {@see Expiry}, the same rule `consume()`
 * uses, and sums are bcmath strings.
 */
final class SellableStock
{
    /**
     * @return Collection<int, array{sellable: string, expired: string, next_expiry: ?string}> keyed by variation id
     */
    public static function forShop(Shop $shop): Collection
    {
        $boundary = Expiry::firstSellableDate($shop)->toDateString();

        return CostLayer::query()
            ->where('shop_id', $shop->id)
            ->where('remaining_quantity', '>', 0)
            ->get(['variation_id', 'remaining_quantity', 'expiry_date'])
            ->groupBy('variation_id')
            ->map(function (Collection $layers) use ($boundary): array {
                $sellable = '0.000';
                $expired = '0.000';
                $next = null;

                foreach ($layers as $layer) {
                    $remaining = Quantity::normalize($layer->getRawOriginal('remaining_quantity'));
                    $expiry = $layer->expiry_date?->toDateString();

                    if ($expiry !== null && $expiry < $boundary) {
                        $expired = bcadd($expired, $remaining, 3);

                        continue;
                    }

                    $sellable = bcadd($sellable, $remaining, 3);

                    if ($expiry !== null && ($next === null || $expiry < $next)) {
                        $next = $expiry;
                    }
                }

                return ['sellable' => $sellable, 'expired' => $expired, 'next_expiry' => $next];
            });
    }
}
