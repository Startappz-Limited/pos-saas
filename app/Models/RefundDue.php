<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Money owed back to a customer (stage 10, step 4; decided 4 November 2026):
 * a voided sale's cash nobody paid out at the void, and every card, M-Pesa,
 * bank transfer or cheque payment it took. Pending until paid (cash out of
 * the payer's own open session; anything else done outside the system and
 * recorded) or, for a cheque, settled as returned.
 */
class RefundDue extends Model
{
    use Auditable;
    use BelongsToShop;
    use HasFactory;

    protected $table = 'refunds_due';

    public const SOURCE_VOID = 'void';

    /** Methods that are money received; "credit" is not, and never creates one. */
    public const METHODS = ['cash', 'card', 'mobile_money', 'bank_transfer', 'cheque'];

    protected $fillable = [
        'uuid',
        'shop_id',
        'sale_id',
        'sale_payment_id',
        'source',
        'method',
        'amount',
        'reference',
        'status',
        'resolution',
        'resolved_method',
        'resolved_reference',
        'cash_payout_id',
        'created_by',
        'resolved_by',
        'resolved_at',
        'notes',
        'reminded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'resolved_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (RefundDue $refund): void {
            $refund->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(CashPayout::class, 'cash_payout_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function methodLabel(): string
    {
        return match ($this->method) {
            'cash' => __('Cash'),
            'card' => __('Card'),
            'mobile_money' => __('M-Pesa'),
            'bank_transfer' => __('Bank transfer'),
            'cheque' => __('Cheque'),
            default => (string) $this->method,
        };
    }
}
