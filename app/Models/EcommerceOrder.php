<?php

namespace App\Models;

use App\Enums\AlertType;
use App\Enums\EcommerceOrderStatus;
use App\Models\Concerns\BelongsToAccessibleShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EcommerceOrder extends Model
{
    use BelongsToAccessibleShop;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'shop_id',
        'platform',
        'platform_order_id',
        'order_number',
        'status',
        'payment_method',
        'payment_status',
        'currency',
        'subtotal',
        'discount_total',
        'shipping_total',
        'tax_total',
        'total',
        'customer_name',
        'customer_email',
        'customer_phone',
        'billing_address',
        'shipping_address',
        'notes',
        'platform_created_at',
        'platform_updated_at',
        'platform_data',
        'sale_id',
        'converted_at',
        'converted_by',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EcommerceOrderStatus::class,
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'platform_data' => 'array',
            'platform_created_at' => 'datetime',
            'platform_updated_at' => 'datetime',
            'converted_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcommerceOrderItem::class);
    }

    public function alerts(): MorphMany
    {
        return $this->morphMany(Alert::class, 'alertable');
    }

    public function orderNotes(): MorphMany
    {
        return $this->alerts()->where('type', AlertType::ORDER_NOTE->value);
    }

    public function reminders(): MorphMany
    {
        return $this->alerts()->where('type', AlertType::ORDER_REMINDER->value);
    }

    // Accessors

    public function getIsConvertedAttribute(): bool
    {
        return $this->sale_id !== null;
    }

    public function getIsCodAttribute(): bool
    {
        return strtolower($this->payment_method ?? '') === 'cod';
    }

    public function getCanBeConvertedAttribute(): bool
    {
        return ! $this->is_converted && $this->status->canBeConverted();
    }

    // Scopes

    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeStatus($query, EcommerceOrderStatus|string $status)
    {
        $value = $status instanceof EcommerceOrderStatus ? $status->value : $status;

        return $query->where('status', $value);
    }

    public function scopeUnconverted($query)
    {
        return $query->whereNull('sale_id');
    }

    public function scopeConverted($query)
    {
        return $query->whereNotNull('sale_id');
    }

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->hasShopRestrictions()) {
            return $query;
        }

        return $query->whereIn($this->qualifyColumn('shop_id'), $user->accessibleShopIds());
    }

    // Methods

    public function markConverted(Sale $sale, User $user): void
    {
        $this->update([
            'sale_id' => $sale->id,
            'converted_at' => now(),
            'converted_by' => $user->id,
        ]);
    }

    public function updatePlatformStatus(string $status): void
    {
        $this->update([
            'status' => $status,
            'last_synced_at' => now(),
        ]);
    }
}
