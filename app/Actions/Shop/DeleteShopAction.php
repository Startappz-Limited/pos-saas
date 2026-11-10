<?php

namespace App\Actions\Shop;

use App\Exceptions\BusinessRuleException;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

class DeleteShopAction
{
    public function __construct(private TenantContext $tenant) {}

    public function execute(Shop $shop): bool
    {
        $business = $this->tenant->business();

        if ($business !== null && $business->default_shop_id === $shop->id && $business->onFreeTrial()) {
            throw BusinessRuleException::because(__('This is the default shop on the Free Trial. Choose another default shop before deleting it.'), 'default_shop_on_trial');
        }

        return DB::transaction(function () use ($shop) {
            // Detach all users from the shop
            $shop->users()->detach();

            // Its Till or Paybill is free for another shop to register.
            $shop->forceFill(['mpesa_shortcode' => null, 'mpesa_shortcode_type' => null, 'mpesa_verified_at' => null])->saveQuietly();

            // Soft delete the shop
            return $shop->delete();
        });
    }
}
