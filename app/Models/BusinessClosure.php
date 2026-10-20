<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A business's closure, from confirmation to erasure (stage 3). Platform-wide
 * and kept after the business is erased (no tenant scope, no foreign key to
 * the business): it is the record that the erasure happened.
 */
class BusinessClosure extends Model
{
    public const REASON_OWNER = 'owner';

    /** Started by billing after 30 business days of lapse (Phase 4). */
    public const REASON_LAPSED = 'lapsed';

    protected $fillable = [
        'business_id',
        'business_uuid',
        'business_name',
        'requested_by',
        'reason',
        'delete_after',
        'cancelled_at',
        'cancelled_by',
        'erased_at',
        'summary',
    ];

    protected $casts = [
        'delete_after' => 'datetime',
        'cancelled_at' => 'datetime',
        'erased_at' => 'datetime',
        'summary' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $closure): void {
            if (empty($closure->uuid)) {
                $closure->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Confirmed, not cancelled and not yet erased.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('cancelled_at')->whereNull('erased_at');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
