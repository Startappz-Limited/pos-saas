<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A FIFO cost layer: units received into a shop for one variation at one
 * cost, consumed oldest first (stage 1b, docs/saas/fifo-analysis.md).
 * Written only by App\Services\Stock\StockLedger.
 */
class CostLayer extends Model
{
    use BelongsToBusiness;
    use BelongsToShop;

    public const SOURCE_INTAKE = 'intake';

    public const SOURCE_ADJUSTMENT = 'adjustment';

    public const SOURCE_SALE_RETURN = 'sale_return';

    public const SOURCE_OPENING = 'opening';

    protected $fillable = [
        'uuid',
        'business_id',
        'shop_id',
        'product_id',
        'variation_id',
        'source_type',
        'source_id',
        'stock_intake_id',
        'unit_cost',
        'original_quantity',
        'remaining_quantity',
        'received_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'original_quantity' => 'integer',
            'remaining_quantity' => 'integer',
            'received_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id')->withoutGlobalScope(ProductVariation::HIDE_DEFAULT);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
