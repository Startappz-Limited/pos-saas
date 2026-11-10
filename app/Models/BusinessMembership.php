<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A user's membership in a business (decision 4.4). Only an active membership
 * opens the business.
 */
class BusinessMembership extends Pivot
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** Suspended by the business (stage 3); the login and other businesses are unaffected. */
    public const STATUS_SUSPENDED = 'suspended';

    protected $table = 'business_user';

    public $incrementing = true;

    protected $fillable = [
        'business_id',
        'user_id',
        'status',
        'invited_by',
        'joined_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * Removed, not merely deactivated (stage 10a, step 1): `removed_at` is
     * set only by RemoveFromBusiness and `members:clean-removed`'s backfill,
     * and cleared only by accepting a re-invite.
     */
    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
