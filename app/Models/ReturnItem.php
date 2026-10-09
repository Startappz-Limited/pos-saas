<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'return_id',
        'sale_item_id',
        'product_id',
        'stock_intake_id',
        'quantity',
        'unit_price',
        'total_price',
        'condition',
        'condition_notes',
        'is_restockable',
        'is_restocked',
        'return_to_supplier',
        'return_to_supplier_at',
        'return_to_supplier_notes',
        'restocked_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'is_restockable' => 'boolean',
            'is_restocked' => 'boolean',
            'return_to_supplier' => 'boolean',
            'return_to_supplier_at' => 'datetime',
            'restocked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReturnItem $item): void {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'return_id');
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockIntake(): BelongsTo
    {
        return $this->belongsTo(StockIntake::class);
    }

    public function purchaseReturnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
