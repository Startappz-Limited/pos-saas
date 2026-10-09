<?php

namespace App\Models;

use App\Enums\AuditEvent;
use App\Enums\AuditStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * An append-only audit record on the isolated `audit` connection.
 *
 * Audit rows are immutable by design: once written they can never be updated or
 * deleted through the application, which is what makes them usable as evidence.
 * Column naming follows database/migrations/audit/ — polymorphic `auditable_*`
 * plus an `event`, not the entity/action naming used elsewhere.
 */
class AuditLog extends Model
{
    protected $connection = 'audit';

    /** Only created_at is tracked — an audit row is never updated. */
    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'user_id',
        'user_name',
        'user_email',
        'auditable_type',
        'auditable_id',
        'auditable_uuid',
        'event',
        'status',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'tags',
        'shop_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'tags' => 'array',
            'event' => AuditEvent::class,
            'status' => AuditStatus::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            if (empty($log->uuid)) {
                $log->uuid = (string) Str::uuid();
            }

            if (empty($log->created_at)) {
                $log->created_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Block any attempt to modify an existing audit row.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException('Audit logs are immutable and cannot be updated.');
        }

        return parent::save($options);
    }

    /**
     * Block deletion for the same reason.
     */
    public function delete(): bool
    {
        throw new RuntimeException('Audit logs are immutable and cannot be deleted.');
    }

    // Scopes

    public function scopeForAuditable(Builder $query, string $type, ?string $uuid = null): Builder
    {
        $query->where('auditable_type', $type);

        return $uuid ? $query->where('auditable_uuid', $uuid) : $query;
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    /**
     * The short class name, for display — `App\Models\Sale` reads as `Sale`.
     */
    public function getAuditableLabelAttribute(): string
    {
        return class_basename($this->auditable_type);
    }
}
