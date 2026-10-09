<?php

use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Create permissions for testing
    Permission::create(['name' => 'shops.view']);
    Permission::create(['name' => 'shops.create']);
    Permission::create(['name' => 'shops.update']);
    Permission::create(['name' => 'shops.delete']);
    Permission::create(['name' => 'shops.activate']);
});

test('user can view shops index if authorized', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shops.view');

    $response = $this->actingAs($user)->get(route('shops.index'));

    $response->assertStatus(200);
});

test('user cannot view shops index if not authorized', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('shops.index'));

    $response->assertStatus(403);
});

test('user can create shop if authorized', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['shops.create', 'shops.view']);
    $manager = User::factory()->create();

    $shopData = [
        'name' => 'Test Shop',
        'code' => 'TEST-001',
        'description' => 'A test shop description',
        'phone' => '0712345678',
        'email' => 'testshop@example.com',
        'address' => '123 Test Street',
        'city' => 'Nairobi',
        'state' => 'Nairobi County',
        'country' => 'Kenya',
        'postal_code' => '00100',
        'status' => ShopStatus::ACTIVE->value,
        'manager_id' => $manager->id,
    ];

    $response = $this->actingAs($user)->post(route('shops.store'), $shopData);

    $response->assertRedirect();
    $this->assertDatabaseHas('shops', [
        'name' => 'Test Shop',
        'code' => 'TEST-001',
    ]);
});

test('user cannot create shop if not authorized', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('shops.store'), [
        'name' => 'Test Shop',
        'code' => 'TEST-001',
    ]);

    $response->assertStatus(403);
});

test('shop code must be unique', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['shops.create', 'shops.view']);

    Shop::factory()->create(['code' => 'EXISTING-001']);

    $response = $this->actingAs($user)->post(route('shops.store'), [
        'name' => 'Test Shop',
        'code' => 'EXISTING-001',
    ]);

    $response->assertSessionHasErrors('code');
});

test('shop code must contain only uppercase letters numbers and hyphens', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['shops.create', 'shops.view']);

    $response = $this->actingAs($user)->post(route('shops.store'), [
        'name' => 'Test Shop',
        'code' => 'invalid code',
    ]);

    $response->assertSessionHasErrors('code');
});

test('user can update shop if authorized', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['shops.update', 'shops.view']);
    $shop = Shop::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($user)->put(route('shops.update', $shop), [
        'name' => 'New Name',
        'code' => $shop->code,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('shops', ['name' => 'New Name']);
});

test('user cannot update shop if not authorized', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->put(route('shops.update', $shop), [
        'name' => 'New Name',
        'code' => $shop->code,
    ]);

    $response->assertStatus(403);
});

test('user can delete shop if authorized', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shops.delete');
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->delete(route('shops.destroy', $shop));

    $response->assertRedirect(route('shops.index'));
    $this->assertSoftDeleted('shops', ['id' => $shop->id]);
});

test('user cannot delete shop if not authorized', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->delete(route('shops.destroy', $shop));

    $response->assertStatus(403);
});

test('user can activate shop if authorized', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shops.activate');
    $shop = Shop::factory()->inactive()->create();

    $response = $this->actingAs($user)->post(route('shops.activate', $shop));

    $response->assertRedirect();
    $this->assertDatabaseHas('shops', [
        'id' => $shop->id,
        'status' => ShopStatus::ACTIVE->value,
    ]);
});

test('user can deactivate shop if authorized', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shops.activate');
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->post(route('shops.deactivate', $shop));

    $response->assertRedirect();
    $this->assertDatabaseHas('shops', [
        'id' => $shop->id,
        'status' => ShopStatus::INACTIVE->value,
    ]);
});

test('user can suspend shop if authorized', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('shops.activate');
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->post(route('shops.suspend', $shop));

    $response->assertRedirect();
    $this->assertDatabaseHas('shops', [
        'id' => $shop->id,
        'status' => ShopStatus::SUSPENDED->value,
    ]);
});

test('shop can have manager assigned', function () {
    $manager = User::factory()->create();
    $shop = Shop::factory()->create(['manager_id' => $manager->id]);

    expect($shop->manager->id)->toBe($manager->id);
});

test('shop can have users assigned', function () {
    $shop = Shop::factory()->create();
    $users = User::factory()->count(3)->create();

    $shop->users()->attach($users->pluck('id'));

    expect($shop->users)->toHaveCount(3);
});

test('shop factory creates valid shop', function () {
    $shop = Shop::factory()->create();

    expect($shop->uuid)->not->toBeNull()
        ->and($shop->name)->not->toBeNull()
        ->and($shop->code)->not->toBeNull()
        ->and($shop->status)->toBe(ShopStatus::ACTIVE);
});

test('shop factory can create inactive shop', function () {
    $shop = Shop::factory()->inactive()->create();

    expect($shop->status)->toBe(ShopStatus::INACTIVE);
});

test('shop factory can create suspended shop', function () {
    $shop = Shop::factory()->suspended()->create();

    expect($shop->status)->toBe(ShopStatus::SUSPENDED);
});

test('shop can check if active', function () {
    $activeShop = Shop::factory()->create();
    $inactiveShop = Shop::factory()->inactive()->create();

    expect($activeShop->isActive())->toBeTrue()
        ->and($inactiveShop->isActive())->toBeFalse();
});

test('shop can check if can process transactions', function () {
    $activeShop = Shop::factory()->create();
    $inactiveShop = Shop::factory()->inactive()->create();
    $suspendedShop = Shop::factory()->suspended()->create();

    expect($activeShop->canProcessTransactions())->toBeTrue()
        ->and($inactiveShop->canProcessTransactions())->toBeFalse()
        ->and($suspendedShop->canProcessTransactions())->toBeFalse();
});

test('shop scopes work correctly', function () {
    Shop::factory()->count(3)->create();
    Shop::factory()->count(2)->inactive()->create();
    Shop::factory()->count(1)->suspended()->create();

    expect(Shop::active()->count())->toBe(3)
        ->and(Shop::inactive()->count())->toBe(2)
        ->and(Shop::suspended()->count())->toBe(1);
});
