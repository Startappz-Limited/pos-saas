<?php

namespace App\Models\Concerns;

use App\Services\Stock\StockLedger;
use App\Services\Stock\StockQuery;
use Illuminate\Database\Eloquent\Builder;
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
 *   ledger: an adjustment, with a movement and FIFO layers.
 *
 * The using model defines stockKey() (the shop_variation column to match),
 * stockShopId() and stockVariation().
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
}
