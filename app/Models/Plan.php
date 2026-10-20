<?php

namespace App\Models;

use App\Enums\Feature;
use App\Enums\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A subscription tier (Phase 4, decision 4.6). Platform data, not tenant data:
 * no business owns it, and the platform edits it without a deploy.
 */
class Plan extends Model
{
    protected $fillable = ['key', 'name', 'price_per_shop', 'currency', 'limits', 'features', 'trial_days', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price_per_shop' => 'decimal:2',
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            $plan->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasFeature(Feature $feature): bool
    {
        return in_array($feature->value, $this->features ?? [], true);
    }

    /**
     * The cap for $limit, or null when unlimited. Shops is not a plan limit:
     * the subscription pays for a number of them.
     */
    public function limit(Limit $limit): ?int
    {
        $value = ($this->limits ?? [])[$limit->value] ?? null;

        return $value === null ? null : (int) $value;
    }
}
