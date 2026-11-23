<?php

namespace App\Models;

use App\Casts\Quantity;
use App\Enums\AlertStatus;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LowStockAlert extends Model
{
    use BelongsToShop;
    use HasFactory, HasUuids;

    protected $fillable = [
        'uuid',
        'shop_id',
        'product_id',
        'variation_id',
        'current_quantity',
        'threshold_quantity',
        'status',
        'source',
        'acknowledged_at',
        'acknowledged_by',
        'resolution',
        'resolved_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AlertStatus::class,
            'current_quantity' => Quantity::class,
            'threshold_quantity' => Quantity::class,
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    // Relationships
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
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
    public function scopeForShop($query, $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeWithStatus($query, AlertStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', AlertStatus::PENDING);
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('status', AlertStatus::ACKNOWLEDGED);
    }

    public function scopeResolved($query)
    {
        return $query->where('status', AlertStatus::RESOLVED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [AlertStatus::PENDING, AlertStatus::ACKNOWLEDGED]);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // Helper Methods
    public function acknowledge(int $userId): bool
    {
        return $this->update([
            'status' => AlertStatus::ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
        ]);
    }

    public function resolve(): bool
    {
        return $this->update([
            'status' => AlertStatus::RESOLVED,
            'resolved_at' => now(),
        ]);
    }

    /**
     * Resolved because the item's sellable stock is back above its reorder level: no person did it, so no user is named.
     */
    public function resolveRecovered(): bool
    {
        return $this->update([
            'status' => AlertStatus::RESOLVED,
            'resolution' => 'stock_recovered',
            'resolved_at' => now(),
            'updated_by' => null,
        ]);
    }

    public function ignore(): bool
    {
        return $this->update([
            'status' => AlertStatus::IGNORED,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === AlertStatus::PENDING;
    }

    public function isAcknowledged(): bool
    {
        return $this->status === AlertStatus::ACKNOWLEDGED;
    }

    public function isResolved(): bool
    {
        return $this->status === AlertStatus::RESOLVED;
    }

    public function isIgnored(): bool
    {
        return $this->status === AlertStatus::IGNORED;
    }
}
