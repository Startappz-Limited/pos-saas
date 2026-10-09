<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccessibleShop;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySnapshot extends Model
{
    use BelongsToAccessibleShop;
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'shop_id',
        'product_id',
        'variation_id',
        'quantity_on_hand',
        'total_value',
        'retail_value',
        'snapshot_date',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'total_value' => 'decimal:2',
            'retail_value' => 'decimal:2',
            'snapshot_date' => 'date',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeForShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('snapshot_date', $date);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('snapshot_date', 'desc');
    }
}
