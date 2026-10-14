<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\TaxClass;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductVariation extends Model
{
    public const HIDE_DEFAULT = 'hide-default-variation';

    use BelongsToBusiness;
    use HasFactory, HasUuids;

    protected $fillable = [
        'business_id',
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
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'attributes' => 'array',
            'is_default' => 'boolean',
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

        // A product's default variation (what a simple product sells as) is
        // bookkeeping for per-shop stock, not a variation anyone chose: keep it
        // out of variation lists, API responses and validation (stage 1a).
        // Product::defaultVariation() and sellableVariations() reach it.
        static::addGlobalScope(self::HIDE_DEFAULT, fn (Builder $query) => $query->where($query->qualifyColumn('is_default'), false));

        static::creating(function ($variation) {
            if (empty($variation->uuid)) {
                $variation->uuid = (string) Str::uuid();
            }
        });

        // Every variation has a stock row in each of its product's shops.
        static::created(fn (self $variation) => $variation->product()->withoutGlobalScopes()->first()?->syncShopVariationRows());
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

    /**
     * A product variation belongs to its product's business.
     */
    protected function inferBusinessId(): ?int
    {
        return empty($this->product_id) ? null : Product::withoutGlobalScopes()->whereKey($this->product_id)->value('business_id');
    }
}
