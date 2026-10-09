<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToAccessibleShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use Auditable;
    use BelongsToAccessibleShop;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'shop_id',
        'register_id',
        'customer_id',
        'source_id',
        'delivery_location',
        'walk_in_customer_name',
        'walk_in_customer_email',
        'walk_in_customer_phone',
        'customer_tax_pin',
        'delivery_company_id',
        'invoice_number',
        'sale_type',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'tax_inclusive',
        'taxable_amount',
        'tax_breakdown',
        'delivery_fee',
        'packaging_fee',
        'other_expenses',
        'expense_notes',
        'total_amount',
        'total_cost',
        'total_profit',
        'paid_amount',
        'balance_due',
        'payment_status',
        'payment_method',
        'is_cod',
        'status',
        'notes',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'is_cod' => 'boolean',
        'tax_inclusive' => 'boolean',
        'tax_breakdown' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'register_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SaleSource::class, 'source_id');
    }

    public function deliveryCompany(): BelongsTo
    {
        return $this->belongsTo(DeliveryCompany::class, 'delivery_company_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ecommerceOrder(): HasOne
    {
        return $this->hasOne(EcommerceOrder::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->hasShopRestrictions()) {
            return $query;
        }

        return $query->whereIn($this->qualifyColumn('shop_id'), $user->accessibleShopIds());
    }
}
