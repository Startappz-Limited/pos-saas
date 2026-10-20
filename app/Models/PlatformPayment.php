<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Money received by the platform from a business (Phase 4): a subscription
 * payment or an AI top-up, recorded by hand or confirmed through the M-Pesa
 * API. `applied_at` is set once, when the payment took effect, so applying a
 * payment twice (a repeated callback, a double click) does nothing.
 *
 * Not the shop's own sales payments (SalePayment): those are the business's
 * customers paying the business.
 */
class PlatformPayment extends Model
{
    use BelongsToBusiness;

    public const SUBSCRIPTION = 'subscription';

    public const AI_TOPUP = 'ai_topup';

    public const PENDING = 'pending';

    public const CONFIRMED = 'confirmed';

    public const FAILED = 'failed';

    public const MANUAL = 'manual';

    public const MPESA = 'mpesa';

    protected $fillable = [
        'business_id', 'purpose', 'amount', 'currency', 'method', 'status', 'mpesa_receipt',
        'checkout_request_id', 'phone', 'raw', 'recorded_by', 'notes', 'confirmed_at', 'applied_at',
    ];

    protected $hidden = ['raw'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw' => 'array',
            'confirmed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            $payment->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isApplied(): bool
    {
        return $this->applied_at !== null;
    }
}
