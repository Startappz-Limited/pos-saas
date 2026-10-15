<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-shop stock, reorder level and price and cost overrides for one
 * sellable variation (decision 4.2): the only stock source since stage 1b.
 * Quantities are changed only by App\Services\Stock\StockLedger.
 */
class ShopVariation extends Model
{
    use BelongsToShop;

    protected $table = 'shop_variation';

    protected $fillable = [
        'shop_id',
        'product_id',
        'variation_id',
        'stock_quantity',
        'reorder_level',
        'cost_price',
        'selling_price',
        'wholesale_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stock_quantity' => 'integer',
            'reorder_level' => 'integer',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id')->withoutGlobalScope(ProductVariation::HIDE_DEFAULT);
    }
}
