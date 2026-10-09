<?php

namespace App\Models\Concerns;

use App\Models\Scopes\ShopAccessScope;

/**
 * For models with a shop_id: hides rows from shops the signed-in user cannot
 * access. Use withoutGlobalScope(ShopAccessScope::class) only where a query
 * genuinely has to cross shops, and say why.
 */
trait BelongsToAccessibleShop
{
    public static function bootBelongsToAccessibleShop(): void
    {
        static::addGlobalScope(new ShopAccessScope('shop_id'));
    }
}
