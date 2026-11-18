<?php

namespace App\Support\Stock;

use App\Casts\Quantity;
use App\Models\CostLayer;
use App\Models\Shop;
use Illuminate\Support\Collection;

/**
 * What a shop can sell of each variation, by batch (stage 11, step 8): the sellable quantity, the quarantined
 * quantity (held, never sold until an approved release), the expired quantity, and the next expiry still ahead.
 * One query per shop (or per variation), so the pull reads all its variations without a query each.
 *
 * The rule, for each layer with stock left:
 *   - quarantined when quarantine_status is set (it is never sold);
 *   - expired when its expiry is before the shop's first sellable day and no approved release covers it;
 *   - sellable otherwise. An approved release (stock_release_id) keeps its original expiry but is sellable.
 *
 * Expiry is judged by {@see Expiry}, the same rule `consume()` uses, and sums are bcmath strings.
 */
final class SellableStock
{
    /**
     * @return Collection<int, array{sellable: string, quarantined: string, expired: string, next_expiry: ?string}> keyed by variation id
     */
    public static function forShop(Shop $shop, ?int $variationId = null): Collection
    {
        $boundary = Expiry::firstSellableDate($shop)->toDateString();

        return CostLayer::query()
            ->join('products', 'products.id', '=', 'cost_layers.product_id')
            ->where('cost_layers.shop_id', $shop->id)
            ->when($variationId !== null, fn ($query) => $query->where('cost_layers.variation_id', $variationId))
            ->where('cost_layers.remaining_quantity', '>', 0)
            ->get([
                'cost_layers.variation_id', 'cost_layers.remaining_quantity', 'cost_layers.expiry_date',
                'cost_layers.quarantine_status', 'cost_layers.stock_release_id', 'products.is_medical',
            ])
            ->groupBy('variation_id')
            ->map(function (Collection $layers) use ($boundary): array {
                $sellable = '0.000';
                $quarantined = '0.000';
                $expired = '0.000';
                $next = null;

                foreach ($layers as $layer) {
                    $remaining = Quantity::normalize($layer->getRawOriginal('remaining_quantity'));
                    $expiry = $layer->expiry_date?->toDateString();

                    if ($layer->getRawOriginal('quarantine_status') !== null) {
                        $quarantined = bcadd($quarantined, $remaining, 3);

                        continue;
                    }

                    // A released layer is exempt from expiry only for a non-medical product (read now, not stored).
                    $waived = $layer->stock_release_id !== null && ! (bool) $layer->is_medical;

                    if ($expiry !== null && $expiry < $boundary && ! $waived) {
                        $expired = bcadd($expired, $remaining, 3);

                        continue;
                    }

                    $sellable = bcadd($sellable, $remaining, 3);

                    if ($expiry !== null && ($next === null || $expiry < $next)) {
                        $next = $expiry;
                    }
                }

                return ['sellable' => $sellable, 'quarantined' => $quarantined, 'expired' => $expired, 'next_expiry' => $next];
            });
    }

    /**
     * The sellable quantity of one variation, as a scale-3 string (zero when it has no stock).
     */
    public static function sellableOf(Shop $shop, int $variationId): string
    {
        return self::forShop($shop, $variationId)->get($variationId)['sellable'] ?? '0.000';
    }
}
