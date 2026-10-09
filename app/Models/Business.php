<?php

namespace App\Models;

use App\Enums\BusinessExportStatus;
use App\Support\DefaultRoles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The account a shop owner (admin) runs. It owns the owner's shops and staff,
 * plus the lists those shops share: suppliers, categories, attributes, pricing
 * rules, expense categories, delivery companies and sale sources.
 *
 * Closing: the owner downloads an export, then asks to close. The business is
 * frozen for a grace period (only the owner can sign in, to download the
 * final export or cancel) and is purged once purge_after has passed.
 */
class Business extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Days between asking to close a business and its data being deleted.
     */
    public const GRACE_DAYS = 30;

    /**
     * How recent the owner's downloaded export must be to close the business.
     */
    public const EXPORT_VALID_DAYS = 7;

    protected $fillable = [
        'uuid',
        'name',
        'owner_id',
        'closing_requested_at',
        'purge_after',
        'closing_requested_by',
        'created_by',
        'updated_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $business) {
            if (empty($business->uuid)) {
                $business->uuid = (string) Str::uuid();
            }
            if (auth()->check() && empty($business->created_by)) {
                $business->created_by = auth()->id();
            }
        });

        static::updating(function (self $business) {
            if (auth()->check()) {
                $business->updated_by = auth()->id();
            }
        });
    }

    protected static function booted(): void
    {
        // A new business starts with the default sale sources (a sale needs
        // one) and its own copy of the default roles (manager, cashier, ...)
        static::created(function (self $business): void {
            SaleSource::seedDefaultsFor($business);
            DefaultRoles::seedFor($business);
        });
    }

    protected function casts(): array
    {
        return [
            'closing_requested_at' => 'datetime',
            'purge_after' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(BusinessExport::class);
    }

    /**
     * The export built from the frozen data when closing was requested.
     */
    public function finalExport(): HasOne
    {
        return $this->hasOne(BusinessExport::class)->where('is_final', true)->latestOfMany();
    }

    public function closingRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closing_requested_by');
    }

    public function isClosing(): bool
    {
        return $this->closing_requested_at !== null;
    }

    /**
     * The newest export the owner has downloaded recently enough to close
     * the business on the strength of it, if any.
     */
    public function exportThatAllowsClosing(): ?BusinessExport
    {
        return $this->exports()
            ->where('is_final', false)
            ->where('status', BusinessExportStatus::READY)
            ->whereNotNull('downloaded_at')
            ->where('completed_at', '>=', now()->subDays(self::EXPORT_VALID_DAYS))
            ->latest('completed_at')
            ->first();
    }

    public function canBeClosed(): bool
    {
        return ! $this->isClosing() && $this->exportThatAllowsClosing() !== null;
    }

    /**
     * Ids of the business's shops, including soft-deleted ones (their data is
     * still the business's). Read without the access scope, so it works with
     * no one signed in.
     *
     * @return array<int, int>
     */
    public function allShopIds(): array
    {
        return Shop::withoutGlobalScopes()->where('business_id', $this->id)->pluck('id')->all();
    }

    /**
     * Headline numbers of what closing the business deletes.
     *
     * @return array<string, int>
     */
    public function recordCounts(): array
    {
        $shopIds = $this->allShopIds();

        return [
            'shops' => count($shopIds),
            'staff' => DB::table('users')->where('business_id', $this->id)->count(),
            'products' => DB::table('products')->whereIn('shop_id', $shopIds)->count(),
            'customers' => DB::table('customers')->whereIn('shop_id', $shopIds)->count(),
            'sales' => DB::table('sales')->whereIn('shop_id', $shopIds)->count(),
        ];
    }

    /**
     * Businesses whose grace period is over.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDueForPurge(Builder $query): void
    {
        $query->whereNotNull('closing_requested_at')->where('purge_after', '<=', now());
    }
}
