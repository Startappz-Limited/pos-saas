<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A request to void a sale, made by someone without `sales.void` (stage 10,
 * step 2): pending until someone holding it approves (voiding the sale) or
 * rejects it, its requester cancels it, or it expires 48 hours after it was
 * made.
 */
class SaleVoidRequest extends Model
{
    use Auditable;
    use BelongsToShop;
    use HasFactory;

    /**
     * How long an approval counts after the request (the window is checked at
     * the request).
     */
    public const EXPIRES_AFTER_HOURS = 48;

    protected $fillable = [
        'uuid',
        'shop_id',
        'sale_id',
        'requested_by',
        'reason',
        'status',
        'requested_at',
        'expires_at',
        'decided_by',
        'decided_at',
        'decision_note',
        'reminded_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'expires_at' => 'datetime',
        'decided_at' => 'datetime',
        'reminded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SaleVoidRequest $request): void {
            $request->uuid ??= (string) Str::uuid();
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function hasExpired(): bool
    {
        return $this->status === 'expired' || ($this->isPending() && $this->expires_at !== null && now()->greaterThan($this->expires_at));
    }
}
