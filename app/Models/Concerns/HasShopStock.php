<?php

namespace App\Models\Concerns;

use App\Casts\Quantity;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Services\Stock\StockLedger;
use App\Services\Stock\StockQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * `stock_quantity` on a product or variation, kept for the code, views and
 * flat API that read and write it (stage 1b, decision 4.8). Stock itself lives
 * per shop and variation in `shop_variation`, changed only by StockLedger.
 *
 * - Reading it gives the stock in the shop the request names, else the total
 *   across the shops the viewer may access (StockQuery). Lists load it in one
 *   query with withStockOnHand().
 * - Setting it (forms, the flat API, factories) moves the stock in the
 *   product's own shop to that figure when the record is saved, through the
 *   ledger: an adjustment, with a movement and FIFO layers. Kept for tests and
 *   other callers with no cost to give; `App\Actions\Product\Concerns\
 *   AppliesOpeningStock` (stage 11, step 4) is what the product form and the
 *   flat API use instead, since it can pass the cost entered.
 *
 * Also the shop-aware stock questions (stage 11, step 4), replacing the old
 * shop-blind `isOutOfStock()`/`canBeSold()`/`getTotalStockValueAttribute()`:
 * `availableAt()`, `canBeSoldAt()`, `stockValueAt()`. Expired batches aren't
 * shown apart yet (stage 11, step 5 adds expiry to cost layers).
 *
 * The using model defines stockKey() (the shop_variation column to match),
 * stockShopId(), stockVariation() and tracksStock().
 */
trait HasShopStock
{
    private ?int $pendingStock = null;

    public static function bootHasShopStock(): void
    {
        static::saved(fn (self $model) => $model->applyPendingStock());
    }

    public function initializeHasShopStock(): void
    {
        $this->append('stock_quantity');
        $this->makeHidden('stock_on_hand');
    }

    public function getStockQuantityAttribute(): int
    {
        if (array_key_exists('stock_on_hand', $this->attributes)) {
            return (int) $this->attributes['stock_on_hand'];
        }

        if (! $this->exists) {
            return (int) $this->pendingStock;
        }

        $query = app(StockQuery::class);

        return $this->stockKey() === 'product_id' ? $query->forProduct($this->id) : $query->forVariation($this->id);
    }

    public function setStockQuantityAttribute(mixed $value): void
    {
        $this->pendingStock = $value === null || $value === '' ? null : (int) $value;
        unset($this->attributes['stock_on_hand']);
    }

    /**
     * Loads stock_quantity for every row in one query.
     */
    public function scopeWithStockOnHand(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        return $query->addSelect(['stock_on_hand' => app(StockQuery::class)->subquery($this->stockKey(), $this->getQualifiedKeyName())]);
    }

    /**
     * Rows whose stock is at or below $threshold (a column or a number).
     */
    public function scopeWhereStock(Builder $query, string $operator, string|int $threshold): Builder
    {
        $sub = app(StockQuery::class)->subquery($this->stockKey(), $this->getQualifiedKeyName());
        $right = is_int($threshold) ? '?' : $this->qualifyColumn($threshold);

        return $query->whereRaw('('.$sub->toSql().') '.$operator.' '.$right, [...$sub->getBindings(), ...(is_int($threshold) ? [$threshold] : [])]);
    }

    private function applyPendingStock(): void
    {
        if ($this->pendingStock === null) {
            return;
        }

        $target = $this->pendingStock;
        $this->pendingStock = null;
        $shopId = $this->stockShopId();
        $variation = $this->stockVariation();

        if ($shopId === null || $variation === null) {
            Log::warning('Stock figure ignored: no shop to hold it.', ['model' => static::class, 'id' => $this->getKey()]);

            return;
        }

        app(StockLedger::class)->setLevel($shopId, $variation, $target, ['reference' => $this, 'notes' => 'Stock figure set on the product.']);
    }

    /**
     * Stock of $variation (this model's own variation — its default one, for
     * a product — when none given) at $shop, in base units.
     */
    public function availableAt(Shop $shop, ?ProductVariation $variation = null): string
    {
        $variation ??= $this->stockVariation();

        return app(StockLedger::class)->exactQuantity($shop->id, $variation->id);
    }

    /**
     * Whether $variation (this model's own, when it's a variation) can be
     * sold at $shop: the status allows it, and either stock isn't tracked or
     * the shop holds at least $quantity. Informational only — it answers "is
     * this in stock here", not "is this sale allowed": the actual refusal,
     * where the overselling policy (stage 11, step 4 follow-up) applies one,
     * is enforced inside StockLedger itself, under its row lock, so a
     * check-then-sell race between two callers can't both pass this method
     * and then both oversell. Expired batches aren't excluded yet (stage 11,
     * step 5 adds expiry to cost layers).
     */
    public function canBeSoldAt(Shop $shop, ?ProductVariation $variation = null, string $quantity = '1'): bool
    {
        if (! $this->status->canBeSold()) {
            return false;
        }

        if (! $this->tracksStock()) {
            return true;
        }

        return bccomp($this->availableAt($shop, $variation), $quantity, 3) >= 0;
    }

    /**
     * The value of stock held at $shop (every shop the viewer may access,
     * when none given): the batches' remaining quantity times their cost.
     * Batches with no known cost are counted apart, not as zero. Expired
     * batches aren't separated yet (stage 11, step 5).
     *
     * @return array{known: float, unknown_quantity: string}
     */
    public function stockValueAt(?Shop $shop = null): array
    {
        $query = DB::table('cost_layers')->where($this->stockKey(), $this->id)->where('remaining_quantity', '>', 0);
        $shop !== null ? $query->where('shop_id', $shop->id) : $query->whereIn('shop_id', app(StockQuery::class)->shops());

        $known = 0.0;
        $unknownQuantity = '0.000';

        foreach ($query->get(['remaining_quantity', 'unit_cost']) as $layer) {
            $quantity = Quantity::normalize($layer->remaining_quantity);
            if ($layer->unit_cost === null) {
                $unknownQuantity = bcadd($unknownQuantity, $quantity, 3);

                continue;
            }
            $known += (float) bcmul($quantity, (string) $layer->unit_cost, 6);
        }

        return ['known' => round($known, 2), 'unknown_quantity' => $unknownQuantity];
    }
}
