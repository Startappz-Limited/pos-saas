<?php

namespace App\Models\Concerns;

use App\Models\Scopes\ShopAccessScope;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record owned by a shop (decision 4.1): queries see only records of the
 * current business's shops that the viewer may access (ShopAccessScope).
 *
 * A model whose shop is optional sets `protected static bool $shopOptional =
 * true` and also uses BelongsToBusiness, so its shop-less rows stay within the
 * business.
 */
trait BelongsToShop
{
    use ResolvesTenantFromRouteBinding;

    public static function bootBelongsToShop(): void
    {
        static::addGlobalScope(new ShopAccessScope('shop_id', static::$shopOptional ?? false));
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
