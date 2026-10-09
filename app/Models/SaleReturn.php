<?php

namespace App\Models;

use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class SaleReturn extends Model
{
    use Auditable;

    /** @use HasFactory<ReturnFactory> */
    use HasFactory;

    protected $table = 'returns';

    protected $fillable = [
        'uuid',
        'sale_id',
        'customer_id',
        'shop_id',
        'return_number',
        'status',
        'reason',
        'notes',
        'total_amount',
        'restocking_fee',
        'refund_amount',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'received_at',
        'inspected_at',
        'inspected_by',
        'inspection_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'reason' => ReturnReason::class,
            'total_amount' => 'decimal:2',
            'restocking_fee' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
            'inspected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SaleReturn $saleReturn): void {
            if (empty($saleReturn->uuid)) {
                $saleReturn->uuid = (string) Str::uuid();
            }
            if (empty($saleReturn->return_number)) {
                $saleReturn->return_number = 'RET-'.strtoupper(Str::random(8));
            }
        });
    }

    protected static function newFactory(): ReturnFactory
    {
        return ReturnFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class, 'return_id');
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', ReturnStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', ReturnStatus::APPROVED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', ReturnStatus::COMPLETED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', ReturnStatus::REJECTED);
    }

    // Helper methods

    public function isPending(): bool
    {
        return $this->status === ReturnStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === ReturnStatus::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === ReturnStatus::REJECTED;
    }

    public function isReceived(): bool
    {
        return $this->status === ReturnStatus::RECEIVED;
    }

    public function isInspected(): bool
    {
        return $this->status === ReturnStatus::INSPECTED;
    }

    public function isCompleted(): bool
    {
        return $this->status === ReturnStatus::COMPLETED;
    }

    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    public function canBeRejected(): bool
    {
        return $this->isPending();
    }

    public function canBeReceived(): bool
    {
        return $this->isApproved();
    }

    public function canBeInspected(): bool
    {
        return $this->isReceived();
    }

    public function canBeRefunded(): bool
    {
        return $this->status->canRefund();
    }
}
