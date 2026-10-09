<?php

namespace App\Models;

use App\Enums\StockIntakeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StockIntake extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'intake_number',
        'purchase_order_id',
        'purchase_order_item_id',
        'shop_id',
        'supplier_id',
        'product_id',
        'product_variation_id',
        'status',
        'intake_date',
        'quantity_received',
        'quantity_accepted',
        'quantity_rejected',
        'unit',
        'quality_status',
        'quality_notes',
        'quality_checks',
        'storage_location',
        'bin_location',
        'batch_number',
        'expiry_date',
        'received_by',
        'received_at',
        'completed_by',
        'completed_at',
        'notes',
        'attachments',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockIntakeStatus::class,
            'intake_date' => 'date',
            'quantity_received' => 'decimal:2',
            'quantity_accepted' => 'decimal:2',
            'quantity_rejected' => 'decimal:2',
            'quality_checks' => 'array',
            'expiry_date' => 'date',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
            'attachments' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($stockIntake) {
            if (empty($stockIntake->uuid)) {
                $stockIntake->uuid = (string) Str::uuid();
            }
            if (empty($stockIntake->intake_number)) {
                $stockIntake->intake_number = 'SI-'.strtoupper(Str::random(8));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function purchaseReturnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
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
    public function scopeByStatus($query, StockIntakeStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', StockIntakeStatus::PENDING);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', StockIntakeStatus::IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', StockIntakeStatus::COMPLETED);
    }

    public function scopeByShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeBySupplier($query, $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeWithQualityIssues($query)
    {
        return $query->where('quantity_rejected', '>', 0);
    }

    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('intake_date', [$startDate, $endDate]);
    }

    // Helper Methods
    public function isPending(): bool
    {
        return $this->status === StockIntakeStatus::PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === StockIntakeStatus::IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === StockIntakeStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === StockIntakeStatus::CANCELLED;
    }

    public function canEdit(): bool
    {
        return $this->status->canEdit();
    }

    public function canComplete(): bool
    {
        return $this->status->canComplete();
    }

    public function canCancel(): bool
    {
        return $this->status->canCancel();
    }

    public function hasQualityIssues(): bool
    {
        return $this->quantity_rejected > 0;
    }

    public function getAcceptanceRate(): float
    {
        if ($this->quantity_received == 0) {
            return 0;
        }

        return round(($this->quantity_accepted / $this->quantity_received) * 100, 2);
    }

    public function getRejectionRate(): float
    {
        if ($this->quantity_received == 0) {
            return 0;
        }

        return round(($this->quantity_rejected / $this->quantity_received) * 100, 2);
    }

    // Computed Attributes
    protected function acceptanceRate(): float
    {
        return $this->getAcceptanceRate();
    }

    protected function rejectionRate(): float
    {
        return $this->getRejectionRate();
    }
}
