<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A business's subscription (Phase 4, decision 4.6): its plan, the shops it
 * pays for, and when it ends. There is one per business.
 *
 * Whether it is in good standing is judged from the dates, not from a status
 * a sweep must have updated: a trial or period that has ended is lapsed from
 * that moment.
 */
class Subscription extends Model
{
    use BelongsToBusiness;

    public const TRIALING = 'trialing';

    public const ACTIVE = 'active';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'business_id', 'plan_id', 'shop_count', 'price_at_signup', 'status',
        'trial_ends_at', 'current_period_end', 'lapse_warned_at',
    ];

    protected function casts(): array
    {
        return [
            'price_at_signup' => 'decimal:2',
            'trial_ends_at' => 'datetime',
            'current_period_end' => 'datetime',
            'lapse_warned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            $subscription->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The moment access ends (trial end, or the paid period's end); null for
     * a cancelled subscription with nothing running.
     */
    public function endsAt(): ?Carbon
    {
        return $this->status === self::TRIALING ? $this->trial_ends_at : $this->current_period_end;
    }

    public function isInGoodStanding(): bool
    {
        if ($this->status === self::CANCELLED) {
            return false;
        }

        $endsAt = $this->endsAt();

        return $endsAt !== null && $endsAt->isFuture();
    }

    /**
     * The monthly price for the shops paid for, at the price locked at signup.
     */
    public function monthlyTotal(): float
    {
        return round((float) $this->price_at_signup * $this->shop_count, 2);
    }
}
