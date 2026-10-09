<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccessibleShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProductEcommerceSync extends Model
{
    use BelongsToAccessibleShop;
    use HasFactory, SoftDeletes;

    protected $table = 'product_ecommerce_sync';

    protected $fillable = [
        'uuid',
        'product_id',
        'shop_id',
        'platform',
        'platform_product_id',
        'platform_name',
        'platform_sku',
        'sync_status',
        'sync_direction',
        'last_synced_at',
        'last_sync_attempt_at',
        'last_sync_error',
        'platform_data',
        'sync_metadata',
        'auto_sync',
    ];

    protected $casts = [
        'platform_data' => 'array',
        'sync_metadata' => 'array',
        'last_synced_at' => 'datetime',
        'last_sync_attempt_at' => 'datetime',
        'auto_sync' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the product that owns this sync record
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the shop that owns this sync record
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Check if sync is pending
     */
    public function isPending(): bool
    {
        return $this->sync_status === 'pending';
    }

    /**
     * Check if sync is completed
     */
    public function isSynced(): bool
    {
        return $this->sync_status === 'synced';
    }

    /**
     * Check if sync has errors
     */
    public function hasError(): bool
    {
        return $this->sync_status === 'error';
    }

    /**
     * Check if product is out of sync
     */
    public function isOutOfSync(): bool
    {
        return $this->sync_status === 'out_of_sync';
    }

    /**
     * Mark sync as successful
     */
    public function markSynced(?array $platformData = null): void
    {
        $this->update([
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'last_sync_attempt_at' => now(),
            'last_sync_error' => null,
            'platform_data' => $platformData ?? $this->platform_data,
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'sync_status' => 'error',
            'last_sync_attempt_at' => now(),
            'last_sync_error' => $error,
        ]);
    }

    /**
     * Mark sync as out of sync
     */
    public function markOutOfSync(): void
    {
        $this->update([
            'sync_status' => 'out_of_sync',
        ]);
    }

    /**
     * Add sync metadata entry
     */
    public function addSyncMetadata(string $action, array $data = []): void
    {
        $metadata = $this->sync_metadata ?? [];
        $metadata[] = [
            'action' => $action,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];

        // Keep only last 50 entries
        if (count($metadata) > 50) {
            $metadata = array_slice($metadata, -50);
        }

        $this->update(['sync_metadata' => $metadata]);
    }

    /**
     * Scope to filter by platform
     */
    public function scopePlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope to filter by sync status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('sync_status', $status);
    }

    /**
     * Scope to get auto-sync enabled records
     */
    public function scopeAutoSync($query)
    {
        return $query->where('auto_sync', true);
    }

    /**
     * Scope to get records needing sync
     */
    public function scopeNeedsSync($query)
    {
        return $query->whereIn('sync_status', ['pending', 'out_of_sync', 'error'])
            ->where('auto_sync', true);
    }
}
