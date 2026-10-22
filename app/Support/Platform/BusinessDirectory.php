<?php

namespace App\Support\Platform;

use App\Models\Business;
use App\Models\BusinessMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The platform console's view of every business (Phase 5, B1): plan, shops,
 * registers, members, usage, status and AI balance in one query.
 *
 * Cross-business on purpose: the operator sees every business, and the
 * figures can't come through the tenant scopes (they hide what the viewer
 * can't see, and an operator is in no business). Each subquery names the
 * business it counts for explicitly; every read is audited by the caller.
 */
final class BusinessDirectory
{
    /**
     * Businesses with their figures, optionally filtered.
     *
     * @return Builder<Business>
     */
    public static function query(?string $search = null, ?string $status = null): Builder
    {
        return Business::query()
            ->select('businesses.*')
            ->selectSub(DB::table('shops')->selectRaw('count(*)')->whereColumn('shops.business_id', 'businesses.id')->whereNull('shops.deleted_at'), 'shops_count')
            ->selectSub(DB::table('registers')->join('shops', 'shops.id', '=', 'registers.shop_id')->selectRaw('count(*)')
                ->whereColumn('shops.business_id', 'businesses.id')->whereNull('shops.deleted_at'), 'registers_count')
            ->selectSub(DB::table('business_user')->selectRaw('count(*)')->whereColumn('business_user.business_id', 'businesses.id')
                ->where('business_user.status', BusinessMembership::STATUS_ACTIVE), 'members_count')
            ->selectSub(DB::table('products')->selectRaw('count(*)')->whereColumn('products.business_id', 'businesses.id')->whereNull('products.deleted_at'), 'products_count')
            ->selectSub(DB::table('sales')->selectRaw('count(*)')->whereColumn('sales.business_id', 'businesses.id')
                ->where('sales.created_at', '>=', now()->subDays(30)), 'sales_30_days')
            ->selectSub(DB::table('subscriptions')->join('plans', 'plans.id', '=', 'subscriptions.plan_id')->select('plans.name')
                ->whereColumn('subscriptions.business_id', 'businesses.id')->limit(1), 'plan_name')
            ->selectSub(DB::table('subscriptions')->select('status')->whereColumn('subscriptions.business_id', 'businesses.id')->limit(1), 'subscription_status')
            ->selectSub(DB::table('subscriptions')->selectRaw("case when status = 'trialing' then trial_ends_at else current_period_end end")
                ->whereColumn('subscriptions.business_id', 'businesses.id')->limit(1), 'subscription_ends_at')
            ->selectSub(DB::table('ai_credit_balances')->select('balance')->whereColumn('ai_credit_balances.business_id', 'businesses.id')->limit(1), 'ai_balance')
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('businesses.name', 'like', '%'.$search.'%')->orWhere('businesses.uuid', $search)))
            ->when(filled($status), fn (Builder $query) => $query->where('businesses.status', $status));
    }
}
