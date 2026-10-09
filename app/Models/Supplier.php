<?php

namespace App\Models;

use App\Enums\SupplierStatus;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use BelongsToBusiness;
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'email',
        'phone',
        'mobile',
        'website',
        'contact_person',
        'contact_email',
        'contact_phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'tax_number',
        'registration_number',
        'currency',
        'payment_terms_days',
        'credit_limit',
        'current_balance',
        'total_orders',
        'order_count',
        'average_rating',
        'on_time_deliveries',
        'late_deliveries',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
            'payment_terms_days' => 'integer',
            'credit_limit' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'total_orders' => 'decimal:2',
            'order_count' => 'integer',
            'average_rating' => 'decimal:2',
            'on_time_deliveries' => 'integer',
            'late_deliveries' => 'integer',
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

        static::creating(function (Supplier $supplier): void {
            if (empty($supplier->uuid)) {
                $supplier->uuid = (string) Str::uuid();
            }
            if (empty($supplier->code)) {
                $supplier->code = 'SUP-'.strtoupper(Str::random(8));
            }
            if (empty($supplier->currency)) {
                $supplier->currency = config('app.currency_code', 'KES');
            }
        });
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', SupplierStatus::ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', SupplierStatus::INACTIVE);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', SupplierStatus::SUSPENDED);
    }

    public function scopeBlacklisted($query)
    {
        return $query->where('status', SupplierStatus::BLACKLISTED);
    }

    public function scopeByStatus($query, SupplierStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    public function scopeWithHighRating($query, float $minRating = 4.0)
    {
        return $query->where('average_rating', '>=', $minRating);
    }

    public function scopeWithCreditLimit($query)
    {
        return $query->whereNotNull('credit_limit')
            ->where('credit_limit', '>', 0);
    }

    public function scopeNearCreditLimit($query, float $percentage = 90)
    {
        return $query->whereNotNull('credit_limit')
            ->where('credit_limit', '>', 0)
            ->whereRaw('current_balance >= (credit_limit * ?)', [$percentage / 100]);
    }

    // Helper Methods
    public function isActive(): bool
    {
        return $this->status === SupplierStatus::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === SupplierStatus::SUSPENDED;
    }

    public function isBlacklisted(): bool
    {
        return $this->status === SupplierStatus::BLACKLISTED;
    }

    public function canPlaceOrders(): bool
    {
        return $this->status->canPlaceOrders();
    }

    public function hasReachedCreditLimit(): bool
    {
        if (! $this->credit_limit) {
            return false;
        }

        return $this->current_balance >= $this->credit_limit;
    }

    public function getRemainingCredit(): float
    {
        if (! $this->credit_limit) {
            return 0;
        }

        return max(0, (float) $this->credit_limit - (float) $this->current_balance);
    }

    public function getDeliverySuccessRate(): float
    {
        $totalDeliveries = $this->on_time_deliveries + $this->late_deliveries;

        if ($totalDeliveries === 0) {
            return 0;
        }

        return ($this->on_time_deliveries / $totalDeliveries) * 100;
    }

    public function getAverageOrderValue(): float
    {
        if ($this->order_count === 0) {
            return 0;
        }

        return (float) $this->total_orders / $this->order_count;
    }

    // Computed Attributes
    public function getRemainingCreditAttribute(): float
    {
        return $this->getRemainingCredit();
    }

    public function getDeliverySuccessRateAttribute(): float
    {
        return $this->getDeliverySuccessRate();
    }

    public function getAverageOrderValueAttribute(): float
    {
        return $this->getAverageOrderValue();
    }

    public function getCreditUtilizationAttribute(): float
    {
        if (! $this->credit_limit || $this->credit_limit == 0) {
            return 0;
        }

        return ((float) $this->current_balance / (float) $this->credit_limit) * 100;
    }
}
