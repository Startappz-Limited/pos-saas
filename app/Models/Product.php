<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\TaxClass;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\HasShopStock;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Product extends Model
{
    use Auditable;
    use BelongsToBusiness;
    use HasFactory, HasUuids, SoftDeletes;
    use HasShopStock;

    protected $fillable = [
        'business_id',
        'uuid',
        'name',
        'slug',
        'description',
        'sku',
        'category_id',
        'supplier_id',
        'shop_id',
        'barcode',
        'cost_price',
        'selling_price',
        'wholesale_price',
        'tax_class',
        'stock_quantity',
        'reorder_level',
        'track_stock',
        'has_variations',
        'image',
        'images',
        'unit',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'tax_class' => TaxClass::class,
            'reorder_level' => 'integer',
            'track_stock' => 'boolean',
            'has_variations' => 'boolean',
            'images' => 'array',
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

        static::creating(function (Product $product): void {
            if (empty($product->uuid)) {
                $product->uuid = (string) Str::uuid();
            }
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
            if (empty($product->sku)) {
                $product->sku = static::generateSku();
            }
        });

        // Every product has at least one variation (decision 4.2): a simple
        // product sells as its default variation, so per-shop stock is keyed
        // on (shop, variation) for everything.
        static::created(function (Product $product): void {
            $product->createDefaultVariation();
            $product->syncShopVariationRows();
        });

        static::updating(function (Product $product): void {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
            if ($product->isDirty('sku') && blank($product->sku)) {
                $product->sku = static::generateSku();
            }
        });
    }

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The shop that owns this product. Sales of this product are attributed
     * to this shop in reporting.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_product')
            ->withPivot(['stock_quantity', 'reorder_level', 'cost_price', 'selling_price', 'wholesale_price', 'is_active'])
            ->withTimestamps();
    }

    /**
     * The shop that holds a stock figure set on the product: its own shop,
     * else the first shop it's listed in, else its business's first shop.
     */
    public function stockShopId(): ?int
    {
        $shopId = $this->shop_id
            ?? DB::table('shop_product')->where('product_id', $this->id)->orderBy('shop_id')->value('shop_id')
            ?? DB::table('shops')->where('business_id', $this->business_id)->whereNull('deleted_at')->orderBy('id')->value('id');

        return $shopId === null ? null : (int) $shopId;
    }

    protected function stockKey(): string
    {
        return 'product_id';
    }

    protected function stockVariation(): ?ProductVariation
    {
        return $this->defaultVariation()->first();
    }

    /**
     * The variations people created; the default variation isn't one of them.
     */
    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    /**
     * The variation a simple product sells as (decision 4.2).
     */
    public function defaultVariation(): HasOne
    {
        return $this->hasOne(ProductVariation::class)
            ->withoutGlobalScope(ProductVariation::HIDE_DEFAULT)
            ->where('is_default', true);
    }

    /**
     * Every sellable variation, the default one included.
     */
    public function sellableVariations(): HasMany
    {
        return $this->hasMany(ProductVariation::class)->withoutGlobalScope(ProductVariation::HIDE_DEFAULT);
    }

    /**
     * Creates the default variation. Its SKU is reserved
     * (`DEFAULT-{product uuid}`), so it can never collide with a variation
     * someone creates; stage 1b decides how sales and searches use it.
     */
    public function createDefaultVariation(): ProductVariation
    {
        return $this->defaultVariation()->first() ?? $this->defaultVariation()->create([
            'business_id' => $this->business_id,
            'name' => 'Default',
            'sku' => 'DEFAULT-'.$this->uuid,
            'cost_price' => $this->cost_price,
            'selling_price' => $this->selling_price ?? 0,
            'wholesale_price' => $this->wholesale_price,
            'tax_class' => $this->tax_class,
            'status' => $this->status ?? ProductStatus::ACTIVE,
            'is_default' => true,
        ]);
    }

    /**
     * Ensures a per-shop stock row (shop_variation) for every variation in
     * each of the product's shops: its own shop and the shops it's listed in.
     * Rows start at zero; stage 1b moves stock onto them from the legacy
     * product and variation columns.
     */
    public function syncShopVariationRows(): void
    {
        $shopIds = DB::table('shop_product')->where('product_id', $this->id)->pluck('shop_id')
            ->push($this->shop_id)->filter()->unique();
        $variationIds = DB::table('product_variations')->where('product_id', $this->id)->pluck('id');

        if ($shopIds->isEmpty() || $variationIds->isEmpty()) {
            return;
        }

        $now = now();
        DB::table('shop_variation')->insertOrIgnore($shopIds->crossJoin($variationIds)->map(fn (array $pair): array => [
            'shop_id' => $pair[0],
            'product_id' => $this->id,
            'variation_id' => $pair[1],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function ecommerceSyncs(): HasMany
    {
        return $this->hasMany(ProductEcommerceSync::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', ProductStatus::ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', ProductStatus::INACTIVE);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('status', ProductStatus::OUT_OF_STOCK);
    }

    public function scopeDiscontinued($query)
    {
        return $query->where('status', ProductStatus::DISCONTINUED);
    }

    public function scopeLowStock($query)
    {
        return $query->whereStock('<=', 'reorder_level');
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Products whose purchase cost has never been established.
     *
     * A NULL cost means "not set yet"; a zero cost is treated the same way
     * because nothing in this system legitimately costs nothing to buy, and
     * historically the column defaulted to 0 rather than NULL.
     */
    public function scopeMissingPurchaseCost($query)
    {
        return $query->where(function ($query): void {
            $query->whereNull('cost_price')->orWhere('cost_price', '<=', 0);
        });
    }

    /**
     * Products priced to lose money — the signature of a purchase cost that was
     * derived from a selling price rather than an actual supplier invoice.
     */
    public function scopeCostAboveSellingPrice($query)
    {
        return $query->whereNotNull('cost_price')
            ->where('cost_price', '>', 0)
            ->whereColumn('cost_price', '>', 'selling_price');
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

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->reorder_level;
    }

    public function canBeSold(): bool
    {
        return $this->status->canBeSold() && ($this->stock_quantity > 0 || ! $this->track_stock);
    }

    /**
     * Whether a real purchase cost has been recorded for this product.
     */
    public function hasPurchaseCost(): bool
    {
        return $this->cost_price !== null && (float) $this->cost_price > 0;
    }

    /**
     * A cost above the selling price guarantees a negative profit on every
     * sale, so it is surfaced as a data problem rather than a thin margin.
     */
    public function hasCostAboveSellingPrice(): bool
    {
        return $this->hasPurchaseCost() && (float) $this->cost_price > (float) $this->selling_price;
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

    public function getTotalStockValueAttribute(): float
    {
        return $this->stock_quantity * (float) $this->cost_price;
    }

    /**
     * Generate a unique SKU for the product
     */
    public static function generateSku(?string $prefix = null): string
    {
        $prefix = $prefix ?? 'PRD';
        $timestamp = now()->format('ymd');
        $random = strtoupper(Str::random(4));

        $sku = "{$prefix}-{$timestamp}-{$random}";

        // Ensure uniqueness
        while (static::where('sku', $sku)->exists()) {
            $random = strtoupper(Str::random(4));
            $sku = "{$prefix}-{$timestamp}-{$random}";
        }

        return $sku;
    }

    /**
     * Get sync record for a specific shop and platform
     */
    public function getSyncRecord(int $shopId, string $platform): ?ProductEcommerceSync
    {
        return $this->ecommerceSyncs()
            ->where('shop_id', $shopId)
            ->where('platform', $platform)
            ->first();
    }

    /**
     * Check if product is synced with a specific platform
     */
    public function isSyncedWith(int $shopId, string $platform): bool
    {
        return $this->ecommerceSyncs()
            ->where('shop_id', $shopId)
            ->where('platform', $platform)
            ->where('sync_status', 'synced')
            ->exists();
    }

    /**
     * Get all platforms this product is synced with
     */
    public function getSyncedPlatforms(): array
    {
        return $this->ecommerceSyncs()
            ->where('sync_status', 'synced')
            ->pluck('platform')
            ->unique()
            ->values()
            ->toArray();
    }
}
