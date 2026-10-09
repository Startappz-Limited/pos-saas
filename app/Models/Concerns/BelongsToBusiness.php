<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Models\Scopes\BusinessScope;
use App\Models\Scopes\ShopAccessScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For models owned by a business as a whole (suppliers, categories, ...):
 * hides other businesses' rows and stamps new rows with the creator's business.
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope(new BusinessScope);

        static::creating(function ($model): void {
            if (! empty($model->business_id)) {
                return;
            }

            $user = ShopAccessScope::currentUser();

            if ($user !== null && ! $user->isSuperAdmin()) {
                $model->business_id = $user->currentBusinessId();
            }
        });
    }

    public function initializeBelongsToBusiness(): void
    {
        $this->mergeFillable(['business_id']);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
