<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySnapshot extends Model
{
    use BelongsToShop;
    use HasFactory, HasUuids;

    /** The day's own snapshot, taken by the nightly run. */
    public const KIND_DAILY = 'daily';

    /** The daily snapshot of a month's last day: kept indefinitely (stage 11c, step 3). */
    public const KIND_MONTH_END = 'month_end';

    /** Taken by hand from the snapshot screen: kept indefinitely. */
    public const KIND_MANUAL = 'manual';

    /** Taken when the day ended, by the nightly run. */
    public const SOURCE_ON_TIME = 'on_time';

    /** Rebuilt exactly from the ledger after the scheduler missed the day (stage 11c, step 4). */
    public const SOURCE_REBUILT = 'rebuilt';

    /** The day was before the business's ledger began, so the current figures stand in (stage 11c, step 4). */
    public const SOURCE_LATE = 'late';

    protected $fillable = [
        'uuid',
        'shop_id',
        'product_id',
        'variation_id',
        'quantity_on_hand',
        'total_value',
        'unknown_cost_quantity',
        'shortfall_quantity',
        'retail_value',
        'snapshot_date',
        'kind',
        'source',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => Quantity::class,
            'unknown_cost_quantity' => Quantity::class,
            'shortfall_quantity' => Quantity::class,
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
    /**
     * Daily snapshots taken before $cutoff: the retention rule keeps month-end and manual ones (stage 11c, step 3).
     */
    public function scopeDailyBefore($query, string $cutoff)
    {
        return $query->where('kind', self::KIND_DAILY)->whereDate('snapshot_date', '<', $cutoff);
    }

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
