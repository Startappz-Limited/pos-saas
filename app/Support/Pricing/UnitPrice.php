<?php

namespace App\Support\Pricing;

use App\Casts\Quantity;
use App\Models\Customer;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\ShopUnitPrice;

/**
 * A unit's selling price at a shop (stage 11, step 2): the shop's own override for that unit, else the unit's own
 * price, else the base unit's price times the factor. A wholesale customer takes the wholesale price at whichever level
 * has one set, falling back to that level's selling price.
 *
 * `exact()` is the bcmath figure with its source (stage 11b): PriceResolver works from it. `for()` is the float the
 * APIs' computed price fields have always sent, kept for them.
 */
class UnitPrice
{
    /**
     * The unit's price as a money string (2 decimals), with where it came from: `shop` (the shop's override), `unit`
     * (the unit's own) or `base` (the base unit's price times the factor). Null when the unit has no price at all.
     *
     * @return array{price: string, source: 'shop'|'unit'|'base'}|null
     */
    public static function exact(Shop $shop, ProductUnit $unit, ?Customer $customer = null): ?array
    {
        $wholesale = $customer?->customer_type === 'wholesale';

        $override = ShopUnitPrice::query()->where('shop_id', $shop->id)->where('unit_id', $unit->id)->first();
        if ($override !== null) {
            $price = $wholesale ? ($override->wholesale_price ?? $override->selling_price) : $override->selling_price;
            if ($price !== null) {
                return ['price' => self::money($price), 'source' => 'shop'];
            }
        }

        $price = $wholesale ? ($unit->wholesale_price ?? $unit->selling_price) : $unit->selling_price;
        if ($price !== null) {
            return ['price' => self::money($price), 'source' => 'unit'];
        }

        if ($unit->is_base) {
            return null;
        }

        $base = ProductUnit::query()->where('variation_id', $unit->variation_id)->where('is_base', true)->first();
        $basePrice = $base === null ? null : self::exact($shop, $base, $customer);

        if ($basePrice === null) {
            return null;
        }

        return ['price' => self::money(bcmul($basePrice['price'], Quantity::normalize($unit->factor), 6)), 'source' => 'base'];
    }

    public static function for(Shop $shop, ProductUnit $unit, ?Customer $customer = null): ?float
    {
        $exact = self::exact($shop, $unit, $customer);

        return $exact === null ? null : (float) $exact['price'];
    }

    /**
     * A figure to the penny, rounded half up. Non-negative figures only (prices are never negative here).
     */
    public static function money(int|float|string $amount): string
    {
        $amount = is_string($amount) ? $amount : sprintf('%.6F', (float) $amount);

        return bcadd($amount, '0.005', 2);
    }
}
