<?php

namespace App\Models;

use App\Enums\PurchaseReturnReason;
use App\Enums\PurchaseReturnStatus;
use Database\Factories\PurchaseReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseReturn extends Model
{
    /** @use HasFactory<PurchaseReturnFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'purchase_order_id',
        'supplier_id',
        'shop_id',
        'sale_return_id',
        'return_number',
        'status',
        'reason',
        'notes',
        'total_amount',
        'supplier_credit_amount',
        'supplier_credit_reference',
        'shipment_reference',
        'requested_by',
        'approved_by',
        'approved_at',
        'shipped_by',
        'shipped_at',
        'completed_by',
        'completed_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseReturnStatus::class,
            'reason' => PurchaseReturnReason::class,
            'total_amount' => 'decimal:2',
            'supplier_credit_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'shipped_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseReturn $purchaseReturn): void {
            if (empty($purchaseReturn->uuid)) {
                $purchaseReturn->uuid = (string) Str::uuid();
            }

            if (empty($purchaseReturn->return_number)) {
                $purchaseReturn->return_number = 'PRN-'.strtoupper(Str::random(8));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', PurchaseReturnStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', PurchaseReturnStatus::APPROVED);
    }

    public function scopeShipped($query)
    {
        return $query->where('status', PurchaseReturnStatus::SHIPPED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', PurchaseReturnStatus::COMPLETED);
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseReturnStatus::DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === PurchaseReturnStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === PurchaseReturnStatus::APPROVED;
    }

    public function isShipped(): bool
    {
        return $this->status === PurchaseReturnStatus::SHIPPED;
    }

    public function isCompleted(): bool
    {
        return $this->status === PurchaseReturnStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseReturnStatus::CANCELLED;
    }

    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    public function canApprove(): bool
    {
        return $this->isPending();
    }

    public function canShip(): bool
    {
        return $this->isApproved();
    }

    public function canComplete(): bool
    {
        return $this->isShipped();
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [
            PurchaseReturnStatus::DRAFT,
            PurchaseReturnStatus::PENDING,
            PurchaseReturnStatus::APPROVED,
        ], true);
    }

    public function getTotalQuantityAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
