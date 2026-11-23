<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\TaxClass;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\HasShopStock;
use App\Models\Concerns\RecordsSyncDeletion;
use App\Support\Stock\UnitBreakdown;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
    use RecordsSyncDeletion;

    protected $fillable = [
        'business_id',
        'uuid',
        'name',
        'is_medical',
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
            'is_medical' => 'boolean',
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

    /**
     * A product with real variations sends no barcode of its own in its JSON: that barcode waits for
     * a unit (stage 11, step 7) and nothing sends it until it is assigned.
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        if ($this->has_variations && array_key_exists('barcode', $attributes)) {
            $attributes['barcode'] = null;
        }

        return $attributes;
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

        // A deleted product's units go with it (stage 11, step 2): keeps its
        // barcode free to reuse, since a variation (which doesn't soft-delete)
        // would otherwise still hold it on its base unit. A raw update, not
        // delete(): the base-unit guard is for someone removing a unit from a
        // product still in use, not this cleanup. Units soft-delete (not the
        // variation, which has no deleted_at), so nothing that already names
        // one, or will via unit_id, loses its reference.
        static::deleted(function (Product $product): void {
            DB::table('product_units')
                ->whereIn('variation_id', $product->sellableVariations()->pluck('id'))
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now(), 'updated_at' => now()]);
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
            ->withPivot(['reorder_level', 'cost_price', 'selling_price', 'wholesale_price', 'is_active'])
            // pivot.stock_quantity: the shop's stock of the product, from
            // per-shop stock (stage 1b, decision 4.8), as the flat API returns.
            ->select('shops.*')
            ->selectSub(fn ($query) => $query->from('shop_variation')
                // An integer, as the column was (MySQL sums decimals as strings).
                ->selectRaw('cast(coalesce(sum(shop_variation.stock_quantity), 0) as signed)')
                ->whereColumn('shop_variation.shop_id', 'shop_product.shop_id')
                ->whereColumn('shop_variation.product_id', 'shop_product.product_id'), 'pivot_stock_quantity')
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

    /** Whether this product tracks stock (stage 11, step 4). */
    protected function tracksStock(): bool
    {
        return (bool) $this->track_stock;
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
     * The units of every sellable variation, the default one's included (stage 11, step 8): what
     * the flat API sends as a product's `units`. A deleted unit is left off (soft deletes).
     */
    /**
     * The stock for a stock screen, in the largest sensible unit with the remainder (stage 11, step 2),
     * for a simple product. A product with real variations shows its plain figure, since its variations
     * don't share one unit.
     */
    public function stockLabel(): string
    {
        $quantity = $this->stock_quantity ?? 0;

        if ($this->has_variations || $this->defaultVariation === null) {
            return self::quantityLabel($quantity);
        }

        return UnitBreakdown::describe($this->defaultVariation, $quantity);
    }

    /**
     * Barcodes of this product waiting for a unit (stage 11, step 7).
     */
    public function unassignedBarcodes(): HasMany
    {
        return $this->hasMany(UnassignedBarcode::class);
    }

    public function units(): HasManyThrough
    {
        return $this->hasManyThrough(ProductUnit::class, ProductVariation::class, 'product_id', 'variation_id')
            ->withoutGlobalScope(ProductVariation::HIDE_DEFAULT);
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

    /**
     * Margin as a percentage, with the money rules sales use (stage 11, step 9): bcmath, rounded to
     * two decimals, no float drift.
     */
    public function getProfitMarginAttribute(): float
    {
        if ((float) $this->selling_price <= 0) {
            return 0;
        }

        return (float) bcmul(bcdiv($this->profit, (string) $this->selling_price, 6), '100', 2);
    }

    /**
     * Profit per unit, to the cent, as a sale's line profit is (stage 11, step 9).
     */
    public function getProfitAttribute(): float
    {
        return (float) bcsub(number_format((float) $this->selling_price, 2, '.', ''), number_format((float) $this->cost_price, 2, '.', ''), 2);
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

    /** Its name in the till's sync pull (Phase 7). */
    public function syncEntity(): string
    {
        return 'products';
    }
}
