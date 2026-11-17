<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StockAdjustmentItem extends Model
{
    use BelongsToShop;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'stock_adjustment_id',
        'product_id',
        'variation_id',
        'shop_id',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'item_notes',
        'cost_layer_id',
        'unit_id',
        'unit_quantity',
        'batch_number',
        'expiry_date',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity_before' => Quantity::class,
            'quantity_change' => Quantity::class,
            'unit_quantity' => Quantity::class,
            'quantity_after' => Quantity::class,
            'expiry_date' => 'date',
            'unit_cost' => Quantity::class.':4',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships
    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The variation the line moves (stage 10, step 1g): the product's default
     * one for a product without variations.
     */
    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * The batch a decrease line named, to take from (stage 11, step 5).
     */
    /**
     * The unit the line was entered in (stage 11, step 4); null for a line entered in base units.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id')->withTrashed();
    }

    public function costLayer(): BelongsTo
    {
        return $this->belongsTo(CostLayer::class);
    }
}
