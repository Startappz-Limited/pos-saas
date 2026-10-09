<?php

namespace App\Actions\Product\Concerns;

use App\Models\Product;

/**
 * Strips purchase-cost fields from a product payload when the acting user is
 * not allowed to set them.
 *
 * The create/edit forms already hide the cost inputs, but hiding a field is
 * presentation, not authorization — a crafted POST would otherwise let a
 * cashier rewrite the figure that drives COGS and every profit report. Both
 * the web and API controllers reach these actions through ProductService, so
 * filtering here covers both entry points.
 */
trait GuardsPurchaseCost
{
    /**
     * Remove `cost_price` (product and variation level) unless the current user
     * holds `products.set-cost`.
     *
     * Console contexts — seeders, importers, the purchase-cost repair command —
     * run without an authenticated user and are trusted to set costs directly,
     * so the filter only applies to a real request-bound user.
     */
    protected function filterPurchaseCost(array $data): array
    {
        if ($this->canSetPurchaseCost()) {
            return $data;
        }

        unset($data['cost_price']);

        foreach ($data['variations'] ?? [] as $index => $variation) {
            unset($data['variations'][$index]['cost_price']);
        }

        return $data;
    }

    protected function canSetPurchaseCost(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return true;
        }

        return $user->can('setCost', Product::class);
    }
}
