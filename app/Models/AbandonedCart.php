<?php

namespace App\Models;

use App\Enums\AbandonedCartStatus;
use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Concerns\BelongsToAccessibleShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A website cart that passed the Abandoned Cart Recovery plugin's cut-off.
 *
 * `checkout_link` is a LIVE recovery URL — anyone holding it can restore the
 * customer's cart and check out as them — so it is encrypted at rest and hidden
 * from every array/JSON serialisation. Read it explicitly, and only on a
 * single-cart screen.
 */
class AbandonedCart extends Model
{
    use BelongsToAccessibleShop;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'shop_id',
        'platform',
        'platform_cart_id',
        'status',
        'platform_status',
        'customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'phone_normalized',
        'user_type',
        'capture_source',
        'currency',
        'subtotal',
        'tax_total',
        'total',
        'coupon_code',
        'checkout_link',
        'abandoned_at',
        'recovered_at',
        'platform_order_id',
        'ecommerce_order_id',
        'sale_id',
        'converted_at',
        'converted_by',
        'assigned_to',
        'last_contacted_at',
        'opted_out_at',
        'reminders_sent',
        'last_activity',
        'platform_data',
    ];

    /**
     * Never serialise the recovery link or the raw plugin payload.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'checkout_link',
        'platform_data',
    ];

    protected function casts(): array
    {
        return [
            'status' => AbandonedCartStatus::class,
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'checkout_link' => 'encrypted',
            'platform_data' => 'array',
            'abandoned_at' => 'datetime',
            'recovered_at' => 'datetime',
            'converted_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'reminders_sent' => 'integer',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AbandonedCartItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function ecommerceOrder(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function alerts(): MorphMany
    {
        return $this->morphMany(Alert::class, 'alertable');
    }

    /**
     * Everything that happened to the cart: website activity, staff notes and
     * write-back results. Reminders are listed separately.
     */
    public function timeline(): MorphMany
    {
        return $this->alerts()
            ->whereIn('type', [AlertType::CART_ACTIVITY->value, AlertType::CART_NOTE->value])
            ->latest('id');
    }

    public function cartNotes(): MorphMany
    {
        return $this->alerts()->where('type', AlertType::CART_NOTE->value);
    }

    public function reminders(): MorphMany
    {
        return $this->alerts()->where('type', AlertType::CART_REMINDER->value);
    }

    // Accessors

    public function getIsConvertedAttribute(): bool
    {
        return $this->sale_id !== null;
    }

    public function getHasUnmatchedItemsAttribute(): bool
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->contains(fn (AbandonedCartItem $item): bool => ! $item->isSellable());
    }

    /**
     * A cart recovered online already has a website order behind it; that order
     * is converted from Website Orders, so converting the cart too would sell
     * the same goods twice.
     */
    public function getCanBeConvertedAttribute(): bool
    {
        return ! $this->is_converted
            && ! in_array($this->status, [AbandonedCartStatus::Converted, AbandonedCartStatus::Recovered], true);
    }

    // Scopes

    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where($this->qualifyColumn('shop_id'), $shopId);
    }

    public function scopeStatus(Builder $query, AbandonedCartStatus|string $status): Builder
    {
        $value = $status instanceof AbandonedCartStatus ? $status->value : $status;

        return $query->where($this->qualifyColumn('status'), $value);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn($this->qualifyColumn('status'), [
            AbandonedCartStatus::Abandoned->value,
            AbandonedCartStatus::Contacted->value,
        ]);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->hasShopRestrictions()) {
            return $query;
        }

        return $query->whereIn($this->qualifyColumn('shop_id'), $user->accessibleShopIds());
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('customer_name', 'like', "%{$term}%")
                ->orWhere('customer_email', 'like', "%{$term}%")
                ->orWhere('customer_phone', 'like', "%{$term}%")
                ->orWhere('platform_cart_id', $term);
        });
    }

    /**
     * The list filters shared by the web screen and the API.
     *
     * @param  array<string, mixed>  $filters  status, from, to (dates on abandoned_at), search, assigned_to, shop_id
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['shop_id'] ?? null, fn (Builder $q, $shopId) => $q->where($this->qualifyColumn('shop_id'), (int) $shopId))
            ->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->status($status))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->where($this->qualifyColumn('abandoned_at'), '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->where($this->qualifyColumn('abandoned_at'), '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['assigned_to'] ?? null, fn (Builder $q, $userId) => $q->where($this->qualifyColumn('assigned_to'), (int) $userId))
            ->search($filters['search'] ?? null);
    }

    /**
     * Count per status over the carts the user may see.
     *
     * @return array<string, int>
     */
    public static function statisticsFor(User $user, ?int $shopId = null): array
    {
        $counts = static::query()
            ->visibleTo($user)
            ->when($shopId, fn (Builder $q) => $q->where('shop_id', $shopId))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = ['total' => (int) $counts->sum()];

        foreach (AbandonedCartStatus::cases() as $status) {
            $stats[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $stats;
    }

    // Methods

    /**
     * Add an entry to the cart's timeline.
     *
     * @param  array<string, mixed>  $data
     */
    public function logActivity(
        string $title,
        string $message = '',
        array $data = [],
        AlertType $type = AlertType::CART_ACTIVITY,
        ?AlertSeverity $severity = null,
        ?int $userId = null,
    ): Alert {
        return $this->alerts()->create([
            'shop_id' => $this->shop_id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'severity' => $severity ?? $type->defaultSeverity(),
            'category' => AlertCategory::ORDERS,
            'is_read' => true,
            'data' => $data ?: null,
            'created_by' => $userId,
        ]);
    }

    /**
     * Whether this timeline event has already been recorded (webhooks are retried).
     */
    public function hasActivity(string $eventKey): bool
    {
        return $this->alerts()->where('data->event_key', $eventKey)->exists();
    }

    public function markConverted(Sale $sale, User $user): void
    {
        $this->update([
            'status' => AbandonedCartStatus::Converted,
            'sale_id' => $sale->id,
            'converted_at' => now(),
            'converted_by' => $user->id,
        ]);
    }
}
