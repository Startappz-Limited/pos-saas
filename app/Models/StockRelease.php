<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Enums\QuarantineStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An approved release of quarantined stock (stage 11, quarantine policy): a quantity moved from a quarantined
 * layer to a new sellable layer, with who approved it, when, and why. Written only by App\Services\Stock\QuarantineService,
 * and never changed afterwards.
 */
class StockRelease extends Model
{
    use Auditable;
    use BelongsToBusiness;
    use BelongsToShop;
    use HasUuids;

    protected $fillable = [
        'uuid',
        'business_id',
        'shop_id',
        'product_id',
        'variation_id',
        'from_cost_layer_id',
        'to_cost_layer_id',
        'quantity',
        'quarantine_status',
        'batch_number',
        'expiry_date',
        'reason',
        'request_key',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => Quantity::class,
            'quarantine_status' => QuarantineStatus::class,
            'expiry_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function fromLayer(): BelongsTo
    {
        return $this->belongsTo(CostLayer::class, 'from_cost_layer_id');
    }

    public function toLayer(): BelongsTo
    {
        return $this->belongsTo(CostLayer::class, 'to_cost_layer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id')->withoutGlobalScope(ProductVariation::HIDE_DEFAULT);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
