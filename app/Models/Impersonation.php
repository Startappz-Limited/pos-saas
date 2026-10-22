<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One time a platform operator signed in as a business user (Phase 5, B3).
 * Platform data, not the business's: written and read only by the console
 * (listed in TenantScopeReflectionTest).
 */
class Impersonation extends Model
{
    public const ENDED_MANUALLY = 'manual';

    public const EXPIRED = 'expired';

    public const SIGNED_OUT = 'signed_out';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'operator_id', 'user_id', 'business_id', 'reason', 'started_at', 'expires_at',
        'ended_at', 'end_reason', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $impersonation): void {
            $impersonation->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
