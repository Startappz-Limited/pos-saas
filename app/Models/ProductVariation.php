<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Enums\ProductStatus;
use App\Enums\TaxClass;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\HasShopStock;
use App\Models\Concerns\RecordsSyncDeletion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ProductVariation extends Model
{
    use HasShopStock;

    public const HIDE_DEFAULT = 'hide-default-variation';

    use BelongsToBusiness;
    use HasFactory, HasUuids;
    use RecordsSyncDeletion;

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
            'cost_price' => Quantity::class.':4',
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'tax_class' => TaxClass::class,
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

        // Every variation has a stock row in each of its product's shops, and
        // exactly one base unit (stage 11, step 2).
        static::created(function (self $variation): void {
            $product = $variation->product()->withoutGlobalScopes()->first();
            $product?->syncShopVariationRows();
            $variation->createBaseUnit($product);
        });
    }

    /**
     * The one base unit every variation has (factor 1, the stock's own
     * precision): named after the product's `unit` field, with the
     * variation's own barcode, or the product's when this is the default
     * variation of a product with no real variations of its own (today's
     * behaviour, carried into the new home).
     */
    public function createBaseUnit(?Product $product = null): ProductUnit
    {
        $product ??= $this->product()->withoutGlobalScopes()->first();
        $name = $product?->unit ?: 'piece';
        $barcode = $this->barcode;
        if (blank($barcode) && $this->is_default && $product !== null && ! $product->has_variations) {
            $barcode = $product->barcode;
        }

        return $this->units()->create([
            'business_id' => $this->business_id,
            'name' => $name,
            'factor' => 1,
            'precision' => ProductUnit::guessPrecision($name),
            'barcode' => $barcode,
            'selling_price' => $this->selling_price,
            'wholesale_price' => $this->wholesale_price,
            'is_base' => true,
            'can_sell' => true,
            'can_buy' => true,
            'is_default_purchase' => true,
        ]);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class, 'variation_id');
    }

    public function baseUnit(): HasOne
    {
        return $this->hasOne(ProductUnit::class, 'variation_id')->where('is_base', true);
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
        return $query->whereStock('>', 0);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === ProductStatus::ACTIVE;
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

    protected function stockKey(): string
    {
        return 'variation_id';
    }

    /**
     * A figure set on a variation is held in its product's own shop.
     */
    protected function stockShopId(): ?int
    {
        return Product::withoutGlobalScopes()->find($this->product_id)?->stockShopId();
    }

    protected function stockVariation(): ?ProductVariation
    {
        return $this;
    }

    /** Whether this variation's product tracks stock (stage 11, step 4). */
    protected function tracksStock(): bool
    {
        return (bool) (Product::withoutGlobalScopes()->find($this->product_id)?->track_stock ?? true);
    }

    /**
     * The low-stock threshold for this variation in $shopId, on its per-shop
     * row: what the low-stock check reads (a variation has no threshold of
     * its own). Null falls back to the product's.
     */
    public function setShopReorderLevel(int $shopId, ?int $level): void
    {
        ShopVariation::query()->firstOrCreate(
            ['shop_id' => $shopId, 'variation_id' => $this->id],
            ['product_id' => $this->product_id, 'stock_quantity' => 0],
        )->update(['reorder_level' => $level]);
    }

    /** Its name in the till's sync pull (Phase 7). */
    public function syncEntity(): string
    {
        return 'variations';
    }
}
