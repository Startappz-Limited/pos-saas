<?php

namespace App\Support\Pricing;

use App\Models\Customer;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopUnitPrice;

/**
 * A unit's selling price at a shop (stage 11, step 2): the shop's own
 * override for that unit, else the unit's own price, else the base unit's
 * price times the factor. A wholesale customer takes the wholesale price at
 * whichever level has one set, falling back to that level's selling price.
 *
 * Backs the APIs' computed price fields (step 7) and the web POS until stage
 * 11b's `quote()` (quantity breaks, the price check) replaces it there.
 */
class UnitPrice
{
    public static function for(Shop $shop, ProductUnit $unit, ?Customer $customer = null): ?float
    {
        $wholesale = $customer?->customer_type === 'wholesale';

        $override = ShopUnitPrice::query()->where('shop_id', $shop->id)->where('unit_id', $unit->id)->first();
        if ($override !== null) {
            $price = $wholesale ? ($override->wholesale_price ?? $override->selling_price) : $override->selling_price;
            if ($price !== null) {
                return (float) $price;
            }
        }

        $price = $wholesale ? ($unit->wholesale_price ?? $unit->selling_price) : $unit->selling_price;
        if ($price !== null) {
            return (float) $price;
        }

        if ($unit->is_base) {
            return null;
        }

        $base = ProductUnit::query()->where('variation_id', $unit->variation_id)->where('is_base', true)->first();
        if ($base === null) {
            return null;
        }

        $basePrice = self::for($shop, $base, $customer);

        return $basePrice === null ? null : round($basePrice * (float) $unit->factor, 2);
    }
}
