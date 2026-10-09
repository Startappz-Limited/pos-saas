<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SalePayment extends Model
{
    protected $fillable = [
        'uuid',
        'sale_id',
        'payment_number',
        'amount',
        'payment_method',
        'reference',
        'notes',
        'paid_at',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Payments are exposed by uuid — never the integer id.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Restrict to payments on sales the user may see. sale_payments has no shop_id
     * of its own, so the scope defers to Sale::scopeVisibleTo().
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('sale', fn (Builder $q) => $q->visibleTo($user));
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Get payment method label
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Cash',
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'mobile_money' => 'Mobile Money',
            default => ucfirst(str_replace('_', ' ', $this->payment_method)),
        };
    }
}
