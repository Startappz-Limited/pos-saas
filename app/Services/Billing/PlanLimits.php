<?php

namespace App\Services\Billing;

use App\Enums\Limit;
use App\Exceptions\PlanLimitExceeded;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Refuses creating something past the plan's limit (Phase 4). Hooked onto the
 * models' `creating` events, so every path that adds one (web, both APIs,
 * imports, sync) is covered by the same check.
 *
 * Counts a business's rows with the query builder, across all of its shops
 * whatever the viewer may see: a quota is the business's, not the viewer's.
 */
class PlanLimits
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @throws PlanLimitExceeded
     */
    public function assertCanAdd(Limit $limit, int $businessId, ?int $shopId = null): void
    {
        $this->tenant->run($businessId, function () use ($limit, $businessId, $shopId): void {
            $entitlements = app(Entitlements::class);

            // Nothing to count for a business that isn't metered.
            if ($entitlements->limit($limit) === null) {
                return;
            }

            $entitlements->assertCanAdd($limit, $this->count($limit, $businessId, $shopId));
        });
    }

    /**
     * Registers and WhatsApp sessions belong to a shop; the business is its.
     *
     * @throws PlanLimitExceeded
     */
    public function assertCanAddForShop(Limit $limit, int $shopId): void
    {
        $businessId = DB::table('shops')->where('id', $shopId)->value('business_id');

        if ($businessId !== null) {
            $this->assertCanAdd($limit, (int) $businessId, $shopId);
        }
    }

    public function count(Limit $limit, int $businessId, ?int $shopId = null): int
    {
        return match ($limit) {
            Limit::Shops => DB::table('shops')->where('business_id', $businessId)->whereNull('deleted_at')->count(),
            // Pending invites hold a seat (Phase 6), so invites can't oversell the plan.
            Limit::Staff => DB::table('business_user')->where('business_id', $businessId)->where('status', 'active')->count()
                + DB::table('business_invites')->where('business_id', $businessId)
                    ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count(),
            Limit::Products => DB::table('products')->where('business_id', $businessId)->whereNull('deleted_at')->count(),
            Limit::Registers => DB::table('registers')->where('shop_id', $shopId)->count(),
            Limit::WhatsappSessions => DB::table('baileys_sessions')
                ->whereIn('shop_id', DB::table('shops')->where('business_id', $businessId)->select('id'))
                ->count(),
        };
    }
}
