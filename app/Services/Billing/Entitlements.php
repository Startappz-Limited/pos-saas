<?php

namespace App\Services\Billing;

use App\Enums\Feature;
use App\Enums\Limit;
use App\Exceptions\PlanLimitExceeded;
use App\Exceptions\SubscriptionInactive;
use App\Models\Shop;
use App\Models\Subscription;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * The one check behind every gate (Phase 4, decision 4.6): middleware,
 * policies, the UI, jobs and the sync API all ask `hasFeature()`, `limit()`
 * and `isReadOnly()` here.
 *
 * A business with no subscription is unmetered: the platform operator's own,
 * or one still waiting for its owner to verify their email. Signing up and
 * verifying creates the trial (StartTrial), and the migration gave every
 * existing business one.
 */
class Entitlements
{
    public function __construct(private TenantContext $tenant, private BusinessDays $days) {}

    public function subscription(): ?Subscription
    {
        return $this->tenant->has() ? Subscription::query()->with('plan')->first() : null;
    }

    public function isMetered(): bool
    {
        return $this->subscription() !== null;
    }

    public function hasFeature(Feature $feature): bool
    {
        $subscription = $this->subscription();

        return $subscription === null || $subscription->plan->hasFeature($feature);
    }

    /**
     * The cap for $limit, or null when there is none. Shops is the number the
     * subscription pays for.
     */
    public function limit(Limit $limit): ?int
    {
        $subscription = $this->subscription();

        if ($subscription === null) {
            return null;
        }

        return $limit === Limit::Shops ? $subscription->shop_count : $subscription->plan->limit($limit);
    }

    /**
     * Refuses adding one more when $current already fills the limit.
     *
     * @throws PlanLimitExceeded
     */
    public function assertCanAdd(Limit $limit, int $current): void
    {
        $allowed = $this->limit($limit);

        if ($allowed !== null && $current >= $allowed) {
            throw new PlanLimitExceeded($limit, $allowed);
        }
    }

    /**
     * When the subscription ended, if it has.
     */
    public function lapsedAt(): ?Carbon
    {
        $subscription = $this->subscription();

        if ($subscription === null || $subscription->isInGoodStanding()) {
            return null;
        }

        return $subscription->endsAt() ?? $subscription->updated_at;
    }

    public function isLapsed(): bool
    {
        return $this->lapsedAt() !== null;
    }

    /**
     * The end of the read-only grace period: the lapse plus the configured
     * business days.
     */
    public function graceEndsAt(): ?Carbon
    {
        $lapsedAt = $this->lapsedAt();

        return $lapsedAt === null ? null : $this->days->add($lapsedAt, (int) config('billing.grace_business_days', 30));
    }

    public function isOverShopQuota(): bool
    {
        $subscription = $this->subscription();

        return $subscription !== null
            && Shop::query()->withoutGlobalScopes()->where('business_id', $subscription->business_id)->count() > $subscription->shop_count;
    }

    /**
     * Why the business can't be written to, or null when it can
     * (SubscriptionInactive::LAPSED or ::OVER_QUOTA).
     */
    public function readOnlyReason(): ?string
    {
        if ($this->isLapsed()) {
            return SubscriptionInactive::LAPSED;
        }

        return $this->isOverShopQuota() ? SubscriptionInactive::OVER_QUOTA : null;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnlyReason() !== null;
    }
}
