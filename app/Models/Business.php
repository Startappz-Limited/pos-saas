<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * The tenant (decision 4.1): shops, products, lists and customers belong to a
 * business; people belong to it through memberships (4.4).
 */
class Business extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'uuid',
        'name',
        'settings',
        'status',
        'currency',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $business): void {
            if (empty($business->uuid)) {
                $business->uuid = (string) Str::uuid();
            }
        });

        // Every sale needs a source, so a business starts with the default one.
        static::created(function (self $business): void {
            SaleSource::create([
                'business_id' => $business->id,
                'name' => 'Walk-in',
                'description' => 'Direct walk-in customer',
                'icon' => 'solar:shop-bold-duotone',
                'color' => '#6b7280',
                'is_active' => true,
                'sort_order' => 0,
            ]);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Whether scheduled sweeps run for this business (stage 1c): only an
     * active one. Suspended and closing businesses are skipped; read-only
     * (lapsed) businesses join them when billing arrives (Phase 4).
     */
    public function runsBackgroundWork(): bool
    {
        return $this->isActive();
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(BusinessMembership::class)
            ->withPivot(['status', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }
}
