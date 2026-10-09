<?php

use App\Models\Business;
use App\Models\SaleSource;
use App\Models\Scopes\ShopAccessScope;
use App\Models\Shop;
use App\Models\User;

/**
 * A fresh install must come up as one working business: the seeded owner owns
 * it, its shops and staff belong to it, and the platform super-admin does not.
 */
it('seeds one business owned by the shop owner', function () {
    $this->seed();

    $owner = User::where('email', 'owner@fitness-center.test')->sole();
    $superAdmin = User::where('email', 'admin@fitness-center.test')->sole();
    $business = Business::sole();

    expect($business->owner_id)->toBe($owner->id)
        ->and($owner->business_id)->toBe($business->id)
        ->and($superAdmin->business_id)->toBeNull()
        ->and(Shop::withoutGlobalScope(ShopAccessScope::class)->whereNull('business_id')->count())->toBe(0)
        ->and(SaleSource::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBeGreaterThan(0);

    $this->actingAs($owner)->get(route('shops.index'))->assertOk()->assertSee('Main Branch');
});
