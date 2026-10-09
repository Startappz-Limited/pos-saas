<?php

namespace App\Models;

use Database\Factories\PurchaseReturnItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PurchaseReturnItem extends Model
{
    /** @use HasFactory<PurchaseReturnItemFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'purchase_return_id',
        'purchase_order_item_id',
        'stock_intake_id',
        'return_item_id',
        'product_id',
        'product_variation_id',
        'quantity',
        'unit_cost',
        'line_total',
        'condition',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseReturnItem $item): void {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (PurchaseReturnItem $item): void {
            $item->line_total = (float) $item->quantity * (float) $item->unit_cost;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function stockIntake(): BelongsTo
    {
        return $this->belongsTo(StockIntake::class);
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ReturnItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }
}
