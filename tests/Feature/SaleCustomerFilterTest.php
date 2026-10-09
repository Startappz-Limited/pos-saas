<?php

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * The mobile customer-detail screen lists a customer's unpaid sales by calling
 * GET /api/sales?customer_id=…&payment_status=unpaid. The API used to ignore
 * customer_id entirely, so every customer was shown the whole shop's unpaid
 * sales — each wholesaler appeared to owe the entire book.
 */
beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);

    $this->alice = Customer::factory()->forShop($this->shop)->create(['name' => 'Alice Wholesale']);
    $this->bob = Customer::factory()->forShop($this->shop)->create(['name' => 'Bob Wholesale']);

    $this->aliceSale = Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'customer_id' => $this->alice->id,
        'payment_status' => 'unpaid',
        'total_amount' => 500.00,
        'balance_due' => 500.00,
    ]);

    Sale::factory()->count(3)->create([
        'shop_id' => $this->shop->id,
        'customer_id' => $this->bob->id,
        'payment_status' => 'unpaid',
        'total_amount' => 1000.00,
        'balance_due' => 1000.00,
    ]);

    Permission::findOrCreate('sales.full-access');
    $this->user->givePermissionTo('sales.full-access');
});

it('returns only the requested customer\'s sales over the API', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/sales?customer_id={$this->alice->id}&payment_status=unpaid")
        ->assertOk();

    $rows = $response->json('data.data');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['uuid'])->toBe($this->aliceSale->uuid);
});

it('does not bleed one customer\'s debt onto another', function () {
    $bobRows = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/sales?customer_id={$this->bob->id}&payment_status=unpaid")
        ->assertOk()
        ->json('data.data');

    expect($bobRows)->toHaveCount(3)
        ->and(collect($bobRows)->pluck('customer_id')->unique()->all())->toBe([$this->bob->id]);
});

it('still returns every customer when no customer_id is sent', function () {
    $rows = $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/sales?payment_status=unpaid')
        ->assertOk()
        ->json('data.data');

    expect($rows)->toHaveCount(4);
});

it('filters the web sales index by customer too', function () {
    $response = $this->actingAs($this->user)
        ->get(route('sales.index', ['customer_id' => $this->alice->id]))
        ->assertOk();

    expect($response->viewData('sales')->pluck('id')->all())->toBe([$this->aliceSale->id]);
});
