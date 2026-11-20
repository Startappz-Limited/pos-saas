<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Enums\QuarantineStatus;
use App\Models\Concerns\Auditable;
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
    // Eloquent writes to layers are audited; the ledger's own writes go straight to the table and are not.
    use Auditable;
    use BelongsToBusiness;
    use BelongsToShop;

    public const SOURCE_INTAKE = 'intake';

    public const SOURCE_ADJUSTMENT = 'adjustment';

    public const SOURCE_SALE_RETURN = 'sale_return';

    public const SOURCE_OPENING = 'opening';

    /** Stock released from quarantine: a sellable layer that keeps the batch and expiry it came in with. */
    public const SOURCE_QUARANTINE_RELEASE = 'quarantine_release';

    /** Units given back by release() when their batch no longer exists (stage 10, step 3). */
    public const SOURCE_RELEASE = 'release';

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
        'batch_number',
        'expiry_date',
        'original_quantity',
        'remaining_quantity',
        'received_at',
        'consumed_at',
        'quarantine_status',
        'quarantine_reason',
        'quarantine_verified_at',
        'quarantine_verified_by',
        'stock_release_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => Quantity::class.':4',
            'original_quantity' => Quantity::class,
            'remaining_quantity' => Quantity::class,
            'expiry_date' => 'date',
            'received_at' => 'datetime',
            'consumed_at' => 'datetime',
            'quarantine_status' => QuarantineStatus::class,
            'quarantine_verified_at' => 'datetime',
        ];
    }

    /**
     * Whether the layer is held in quarantine: its stock is on hand but never sold until an approved release.
     */
    public function isQuarantined(): bool
    {
        return $this->quarantine_status !== null;
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
