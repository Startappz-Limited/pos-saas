<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'purchase_order_id',
        'product_id',
        'product_variation_id',
        'sku',
        'product_name',
        'variation_attributes',
        'quantity_ordered',
        'quantity_received',
        'quantity_remaining',
        'unit',
        'unit_cost',
        'tax_rate',
        'tax_amount',
        'discount_percent',
        'discount_amount',
        'line_total',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'variation_attributes' => 'array',
            'quantity_ordered' => 'decimal:2',
            'quantity_received' => 'decimal:2',
            'quantity_remaining' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($item) {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
            if (empty($item->quantity_remaining)) {
                $item->quantity_remaining = $item->quantity_ordered;
            }
        });

        static::saving(function ($item) {
            // Calculate line total
            $subtotal = $item->quantity_ordered * $item->unit_cost;
            $discountPercent = (float) ($item->discount_percent ?? 0);
            $discountAmount = $discountPercent > 0
                ? ($subtotal * $discountPercent / 100)
                : (float) ($item->discount_amount ?? 0);
            $afterDiscount = $subtotal - $discountAmount;
            $taxAmount = $afterDiscount * (float) ($item->tax_rate ?? 0) / 100;

            $item->discount_amount = $discountAmount;
            $item->tax_amount = $taxAmount;
            $item->line_total = $afterDiscount + $taxAmount;

            // Update remaining quantity
            $item->quantity_remaining = $item->quantity_ordered - $item->quantity_received;
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function stockIntakes(): HasMany
    {
        return $this->hasMany(StockIntake::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Helper Methods
    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }

    public function isPartiallyReceived(): bool
    {
        return $this->quantity_received > 0 && $this->quantity_received < $this->quantity_ordered;
    }

    public function hasRemainingQuantity(): bool
    {
        return $this->quantity_remaining > 0;
    }

    public function getReceivingProgress(): float
    {
        if ($this->quantity_ordered == 0) {
            return 0;
        }

        return round(($this->quantity_received / $this->quantity_ordered) * 100, 2);
    }

    public function recordReceivedQuantity(float $quantity): void
    {
        $this->quantity_received += $quantity;
        $this->quantity_remaining = $this->quantity_ordered - $this->quantity_received;
        $this->save();
    }

    // Computed Attributes
    protected function receivingProgress(): float
    {
        return $this->getReceivingProgress();
    }
}
