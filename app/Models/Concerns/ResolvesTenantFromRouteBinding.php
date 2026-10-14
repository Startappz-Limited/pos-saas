<?php

namespace App\Models\Concerns;

use App\Models\Shop;
use App\Support\Tenancy\TenantContext;

/**
 * Route model binding for tenant models (decision 4.3).
 *
 * A signed-in request binds through the tenant scopes, so another business's
 * record is a 404. A request with no signed-in user and no business (an
 * inbound webhook bound by shop uuid, a signed invoice link) finds the record
 * across businesses and then acts for the record's business; such routes
 * check their HMAC signature or URL signature before doing anything with it.
 */
trait ResolvesTenantFromRouteBinding
{
    public function resolveRouteBinding($value, $field = null)
    {
        $tenant = app(TenantContext::class);

        if ($tenant->has() || request()->user() !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        $model = $tenant->bypass(fn () => parent::resolveRouteBinding($value, $field));

        if ($model !== null && ($businessId = $model->owningBusinessId()) !== null) {
            $tenant->set($businessId);
        }

        return $model;
    }

    /**
     * The business this record belongs to, directly or through its shop.
     */
    public function owningBusinessId(): ?int
    {
        if (! empty($this->business_id)) {
            return (int) $this->business_id;
        }

        if ($this instanceof Shop || empty($this->shop_id)) {
            return null;
        }

        $businessId = Shop::withoutGlobalScopes()->whereKey($this->shop_id)->value('business_id');

        return $businessId === null ? null : (int) $businessId;
    }
}
