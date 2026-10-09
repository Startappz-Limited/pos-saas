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

            // Soft delete the shop
            return $shop->delete();
        });
    }
}
