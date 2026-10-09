<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\BelongsToAccessibleShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseOrder extends Model
{
    use BelongsToAccessibleShop;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'order_number',
        'supplier_id',
        'shop_id',
        'status',
        'order_date',
        'expected_delivery_date',
        'actual_delivery_date',
        'subtotal',
        'tax_amount',
        'shipping_cost',
        'discount_amount',
        'total_amount',
        'currency',
        'payment_terms',
        'payment_due_date',
        'payment_status',
        'payment_date',
        'notes',
        'terms_and_conditions',
        'attachments',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
            'payment_due_date' => 'date',
            'payment_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'attachments' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($purchaseOrder) {
            if (empty($purchaseOrder->uuid)) {
                $purchaseOrder->uuid = (string) Str::uuid();
            }
            if (empty($purchaseOrder->order_number)) {
                $purchaseOrder->order_number = 'PO-'.strtoupper(Str::random(8));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockIntakes(): HasMany
    {
        return $this->hasMany(StockIntake::class);
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
    public function scopeByStatus($query, PurchaseOrderStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', PurchaseOrderStatus::DRAFT);
    }

    public function scopePending($query)
    {
        return $query->where('status', PurchaseOrderStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', PurchaseOrderStatus::APPROVED);
    }

    public function scopeOrdered($query)
    {
        return $query->where('status', PurchaseOrderStatus::ORDERED);
    }

    public function scopeReceived($query)
    {
        return $query->whereIn('status', [PurchaseOrderStatus::PARTIALLY_RECEIVED, PurchaseOrderStatus::RECEIVED]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('expected_delivery_date', '<', now())
            ->whereIn('status', [PurchaseOrderStatus::ORDERED, PurchaseOrderStatus::PARTIALLY_RECEIVED]);
    }

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('order_date', [$startDate, $endDate]);
    }

    // Helper Methods
    public function isDraft(): bool
    {
        return $this->status === PurchaseOrderStatus::DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === PurchaseOrderStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === PurchaseOrderStatus::APPROVED;
    }

    public function isOrdered(): bool
    {
        return $this->status === PurchaseOrderStatus::ORDERED;
    }

    public function isFullyReceived(): bool
    {
        return $this->status === PurchaseOrderStatus::RECEIVED;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status === PurchaseOrderStatus::PARTIALLY_RECEIVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseOrderStatus::CANCELLED;
    }

    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    public function canApprove(): bool
    {
        return $this->status->canApprove();
    }

    public function canCancel(): bool
    {
        return $this->status->canCancel();
    }

    public function canReceive(): bool
    {
        return $this->status->canReceive();
    }

    public function isOverdue(): bool
    {
        if (! $this->expected_delivery_date) {
            return false;
        }

        return $this->expected_delivery_date->isPast() &&
            in_array($this->status, [PurchaseOrderStatus::ORDERED, PurchaseOrderStatus::PARTIALLY_RECEIVED]);
    }

    public function getTotalItemsCount(): int
    {
        return $this->items()->count();
    }

    public function getTotalQuantityOrdered(): float
    {
        return $this->items()->sum('quantity_ordered');
    }

    public function getTotalQuantityReceived(): float
    {
        return $this->items()->sum('quantity_received');
    }

    public function getReceivingProgress(): float
    {
        $ordered = $this->getTotalQuantityOrdered();
        if ($ordered == 0) {
            return 0;
        }

        return round(($this->getTotalQuantityReceived() / $ordered) * 100, 2);
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items()->sum('line_total');
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->shipping_cost - $this->discount_amount;
        $this->save();
    }

    // Computed Attributes
    protected function totalItems(): int
    {
        return $this->getTotalItemsCount();
    }

    protected function totalQuantityOrdered(): float
    {
        return $this->getTotalQuantityOrdered();
    }

    protected function totalQuantityReceived(): float
    {
        return $this->getTotalQuantityReceived();
    }

    protected function receivingProgress(): float
    {
        return $this->getReceivingProgress();
    }

    protected function isOverdueAttribute(): bool
    {
        return $this->isOverdue();
    }
}
