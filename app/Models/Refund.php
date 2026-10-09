<?php

namespace App\Models;

use App\Enums\RefundMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Refund extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'return_id',
        'customer_id',
        'shop_id',
        'refund_number',
        'method',
        'amount',
        'status',
        'notes',
        'transaction_id',
        'reference_number',
        'processed_by',
        'processed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'method' => RefundMethod::class,
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Refund $refund): void {
            if (empty($refund->uuid)) {
                $refund->uuid = (string) Str::uuid();
            }
            if (empty($refund->refund_number)) {
                $refund->refund_number = 'REF-'.strtoupper(Str::random(8));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // Relationships

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'return_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Helper methods

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
