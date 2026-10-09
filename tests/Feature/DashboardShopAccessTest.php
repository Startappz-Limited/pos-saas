<?php

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;

test('dashboard only includes assigned shop data for allocated users', function () {
    $assignedShop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $user = User::factory()->create();
    $user->shops()->sync([$assignedShop->id]);

    Sale::factory()->create([
        'shop_id' => $assignedShop->id,
        'status' => 'completed',
        'total_amount' => 100,
    ]);
    Sale::factory()->create([
        'shop_id' => $otherShop->id,
        'status' => 'completed',
        'total_amount' => 250,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSuccessful();

    $statistics = $response->viewData('statistics');

    expect((float) $statistics['total_sales'])->toBe(100.0)
        ->and((int) $statistics['total_orders'])->toBe(1);
});

test('dashboard rejects inaccessible shop filters for allocated users', function () {
    $assignedShop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $user = User::factory()->create();
    $user->shops()->sync([$assignedShop->id]);

    $this->actingAs($user)
        ->get(route('dashboard', ['shop_id' => $otherShop->id]))
        ->assertForbidden();
});

test('dashboard allows unallocated users to filter any shop', function () {
    $shop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $user = User::factory()->create();

    $ownSale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'status' => 'completed',
        'total_amount' => 75,
    ]);
    $otherSale = Sale::factory()->create([
        'shop_id' => $otherShop->id,
        'status' => 'completed',
        'total_amount' => 125,
    ]);

    // With a shop filter active the dashboard sums sale_items.line_total (revenue is
    // attributed to the shop that owns each product), so the sales need lines.
    SaleItem::factory()->forShop($shop)->withTotals(75, 50)->create(['sale_id' => $ownSale->id]);
    SaleItem::factory()->forShop($otherShop)->withTotals(125, 80)->create(['sale_id' => $otherSale->id]);

    $response = $this->actingAs($user)->get(route('dashboard', ['shop_id' => $otherShop->id]));

    $response->assertSuccessful();

    $statistics = $response->viewData('statistics');

    expect((float) $statistics['total_sales'])->toBe(125.0)
        ->and((int) $statistics['total_orders'])->toBe(1);
});
