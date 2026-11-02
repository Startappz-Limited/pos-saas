<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 * A new customer belongs to a shop (`customers.shop_id` is required), so a member
 * who has been given no shop is refused, on the web and the flat API (hotfix
 * fix/api-authz, 2 November 2026). The API used to fail with a 500, and the web
 * fell back to shop 1, which could be another business's: the customer then
 * took that business (its business is read from the shop).
 */

beforeEach(function () {
    seedPermissions();
    $this->member = User::factory()->create();
    $this->member->givePermissionTo(['customers.view', 'customers.create']);
    $this->customer = ['name' => 'Wanjiru Kamau', 'phone' => '0712345678', 'customer_type' => 'retail'];
});

it('refuses a customer from a member with no shop on the API, with a message and no row', function () {
    Sanctum::actingAs($this->member, ['*']);

    $this->postJson('/api/customers', $this->customer)
        ->assertUnprocessable()
        ->assertJsonPath('errors.shop.0', "You haven't been given a shop yet. Ask the owner.");

    expect(DB::table('customers')->where('name', 'Wanjiru Kamau')->exists())->toBeFalse();
});

it('refuses a customer from a member with no shop on the web, and never puts it in another business', function () {
    // Shop 1 may belong to another business; the old fallback used it.
    $this->actingAs($this->member)->from(route('customers.create'))
        ->post(route('customers.store'), $this->customer)
        ->assertRedirect(route('customers.create'))
        ->assertSessionHasErrors(['shop' => "You haven't been given a shop yet. Ask the owner."]);

    expect(DB::table('customers')->where('name', 'Wanjiru Kamau')->exists())->toBeFalse();
});

it('adds the customer to the member\'s own shop when they have one', function () {
    $shop = Shop::factory()->create();
    $this->member->shops()->attach($shop);
    Sanctum::actingAs($this->member, ['*']);

    $this->postJson('/api/customers', $this->customer)->assertCreated();

    expect(DB::table('customers')->where('name', 'Wanjiru Kamau')->value('shop_id'))->toBe($shop->id);
});
