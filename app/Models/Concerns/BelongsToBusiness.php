<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Models\Shop;
use App\Support\Tenancy\MissingTenantContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record owned by one business (decision 4.1).
 *
 * On create, business_id is taken from the record's parent when it has one
 * (its shop, by default; models override inferBusinessId()), otherwise from
 * the current TenantContext. A record that can't be placed in a business is
 * refused rather than saved without one.
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::creating(function (self $model): void {
            if (! empty($model->business_id)) {
                return;
            }

            $model->business_id = $model->inferBusinessId() ?? app(TenantContext::class)->id()
                ?? throw MissingTenantContext::forModel(class_basename($model));
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The business of the record's parent, when it has one.
     */
    protected function inferBusinessId(): ?int
    {
        if (empty($this->shop_id)) {
            return null;
        }

        return Shop::withoutGlobalScopes()->whereKey($this->shop_id)->value('business_id');
    }
}
