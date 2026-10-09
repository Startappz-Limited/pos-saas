<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\TaxClass;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductVariation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'product_id',
        'name',
        'sku',
        'barcode',
        'attributes',
        'cost_price',
        'selling_price',
        'wholesale_price',
        'tax_class',
        'stock_quantity',
        'image',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'attributes' => 'array',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'tax_class' => TaxClass::class,
            'stock_quantity' => 'integer',
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

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($variation) {
            if (empty($variation->uuid)) {
                $variation->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', ProductStatus::ACTIVE);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === ProductStatus::ACTIVE;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_quantity <= 0;
    }

    public function canBeSold(): bool
    {
        return $this->status->canBeSold() && $this->stock_quantity > 0;
    }

    /**
     * Whether a real purchase cost has been recorded for this variation.
     */
    public function hasPurchaseCost(): bool
    {
        return $this->cost_price !== null && (float) $this->cost_price > 0;
    }

    public function getProfitMarginAttribute(): float
    {
        if ($this->selling_price <= 0) {
            return 0;
        }

        return (($this->selling_price - (float) $this->cost_price) / $this->selling_price) * 100;
    }

    public function getProfitAttribute(): float
    {
        return $this->selling_price - (float) $this->cost_price;
    }
}
