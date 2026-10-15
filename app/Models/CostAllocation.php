<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Units a sale, write-off or purchase return took from a cost layer, at the
 * layer's cost (stage 1b). A provisional allocation (sold below zero) has no
 * layer yet and carries the last known cost until stock arrives.
 * Written only by App\Services\Stock\StockLedger.
 */
class CostAllocation extends Model
{
    use BelongsToBusiness;
    use BelongsToShop;

    public const CONSUMER_SALE_ITEM = 'sale_item';

    public const CONSUMER_ADJUSTMENT_ITEM = 'stock_adjustment_item';

    public const CONSUMER_PURCHASE_RETURN_ITEM = 'purchase_return_item';

    protected $fillable = [
        'uuid',
        'business_id',
        'shop_id',
        'product_id',
        'variation_id',
        'cost_layer_id',
        'consumer_type',
        'consumer_id',
        'sale_item_id',
        'quantity_allocated',
        'unit_cost',
        'total_cost',
        'is_provisional',
        'provisional_unit_cost',
        'allocated_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'provisional_unit_cost' => 'decimal:2',
            'quantity_allocated' => 'integer',
            'is_provisional' => 'boolean',
            'allocated_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(CostLayer::class, 'cost_layer_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
