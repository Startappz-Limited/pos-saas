<?php

namespace App\Actions\Shop;

use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class DeleteShopAction
{
    public function execute(Shop $shop): bool
    {
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
