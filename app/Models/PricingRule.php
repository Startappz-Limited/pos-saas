<?php

namespace App\Models;

use App\Enums\PricingType;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PricingRule extends Model
{
    use BelongsToBusiness;
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'type',
        'product_id',
        'price',
        'discount_percentage',
        'discount_amount',
        'min_quantity',
        'max_quantity',
        'customer_id',
        'start_date',
        'end_date',
        'priority',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => PricingType::class,
            'price' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
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

        static::creating(function ($pricingRule) {
            if (empty($pricingRule->uuid)) {
                $pricingRule->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
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
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeByType($query, PricingType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForCustomer($query, ?int $customerId = null)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeCurrentlyValid($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            });
    }

    public function scopeForQuantity($query, int $quantity)
    {
        return $query->where(function ($q) use ($quantity) {
            $q->where(function ($subQ) use ($quantity) {
                $subQ->whereNotNull('min_quantity')
                    ->where('min_quantity', '<=', $quantity);
            })->orWhereNull('min_quantity');
        })->where(function ($q) use ($quantity) {
            $q->where(function ($subQ) use ($quantity) {
                $subQ->whereNotNull('max_quantity')
                    ->where('max_quantity', '>=', $quantity);
            })->orWhereNull('max_quantity');
        });
    }

    public function scopeOrderByPriority($query, string $direction = 'desc')
    {
        return $query->orderBy('priority', $direction);
    }

    // Helper Methods
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->start_date && $this->start_date->isAfter($now)) {
            return false;
        }

        if ($this->end_date && $this->end_date->isBefore($now)) {
            return false;
        }

        return true;
    }

    public function isValidForQuantity(int $quantity): bool
    {
        if ($this->min_quantity && $quantity < $this->min_quantity) {
            return false;
        }

        if ($this->max_quantity && $quantity > $this->max_quantity) {
            return false;
        }

        return true;
    }

    public function isValidForCustomer(?int $customerId = null): bool
    {
        // Customer-specific rules must match the customer
        if ($this->type === PricingType::CUSTOMER_SPECIFIC) {
            return $this->customer_id === $customerId;
        }

        // Non-customer-specific rules are valid for all customers
        return true;
    }

    public function calculateFinalPrice(?int $quantity = 1): float
    {
        $basePrice = (float) $this->price;

        if ($this->discount_percentage) {
            $basePrice -= ($basePrice * ((float) $this->discount_percentage / 100));
        }

        if ($this->discount_amount) {
            $basePrice -= (float) $this->discount_amount;
        }

        return max(0, $basePrice * $quantity);
    }

    // Computed Attributes
    public function getEffectivePriceAttribute(): float
    {
        return $this->calculateFinalPrice(1);
    }

    public function getDiscountValueAttribute(): float
    {
        $originalPrice = (float) $this->price;
        $effectivePrice = $this->effective_price;

        return $originalPrice - $effectivePrice;
    }
}
