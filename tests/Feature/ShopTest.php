<?php

use App\Enums\ShopStatus;
use App\Models\Business;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\InvoiceNumberService;
use Illuminate\Database\UniqueConstraintViolationException;
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
    $user = User::factory()->owner()->create();
    $user->givePermissionTo('shops.view');

    $response = $this->actingAs($user)->get(route('shops.index'));

    $response->assertStatus(200);
});

test('user cannot view shops index if not authorized', function () {
    $user = User::factory()->owner()->create();

    $response = $this->actingAs($user)->get(route('shops.index'));

    $response->assertStatus(403);
});

test('user can create shop if authorized', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['shops.create', 'shops.view']);
    $manager = User::factory()->owner()->create();

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
    $user = User::factory()->owner()->create();

    $response = $this->actingAs($user)->post(route('shops.store'), [
        'name' => 'Test Shop',
        'code' => 'TEST-001',
    ]);

    $response->assertStatus(403);
});

test('shop code must be unique', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['shops.create', 'shops.view']);

    Shop::factory()->create(['code' => 'EXISTING-001']);

    $response = $this->actingAs($user)->post(route('shops.store'), [
        'name' => 'Test Shop',
        'code' => 'EXISTING-001',
    ]);

    $response->assertSessionHasErrors('code');
});

test('shop code must contain only uppercase letters numbers and hyphens', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['shops.create', 'shops.view']);

    $response = $this->actingAs($user)->post(route('shops.store'), [
        'name' => 'Test Shop',
        'code' => 'invalid code',
    ]);

    $response->assertSessionHasErrors('code');
});

test('user can update shop if authorized', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['shops.update', 'shops.view']);
    $shop = Shop::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($user)->put(route('shops.update', $shop), [
        'name' => 'New Name',
        'code' => $shop->code,
        'country' => 'Kenya',
        'status' => ShopStatus::ACTIVE->value,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('shops', ['name' => 'New Name']);
});

test('user cannot update shop if not authorized', function () {
    $user = User::factory()->owner()->create();
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->put(route('shops.update', $shop), [
        'name' => 'New Name',
        'code' => $shop->code,
    ]);

    $response->assertStatus(403);
});

test('user can delete shop if authorized', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo('shops.delete');
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->delete(route('shops.destroy', $shop));

    $response->assertRedirect(route('shops.index'));
    $this->assertSoftDeleted('shops', ['id' => $shop->id]);
});

test('user cannot delete shop if not authorized', function () {
    $user = User::factory()->owner()->create();
    $shop = Shop::factory()->create();

    $response = $this->actingAs($user)->delete(route('shops.destroy', $shop));

    $response->assertStatus(403);
});

test('user can activate shop if authorized', function () {
    $user = User::factory()->owner()->create();
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
    $user = User::factory()->owner()->create();
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
    $user = User::factory()->owner()->create();
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
    $manager = User::factory()->owner()->create();
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

test('a blank country or status is a form error, not a database exception', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo('shops.create');

    $this->actingAs($user)
        ->from(route('shops.create'))
        ->post(route('shops.store'), [
            'name' => 'No Country Shop',
            'code' => 'NO-COUNTRY',
            'country' => '',
            'status' => '',
        ])
        ->assertRedirect(route('shops.create'))
        ->assertSessionHasErrors(['country' => 'The country is required.', 'status']);

    expect(Shop::where('code', 'NO-COUNTRY')->exists())->toBeFalse();
});

test('clearing the country on edit is a form error', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['shops.update', 'shops.view']);
    $shop = Shop::factory()->create(['country' => 'Kenya']);

    $this->actingAs($user)
        ->from(route('shops.edit', $shop))
        ->put(route('shops.update', $shop), [
            'name' => $shop->name,
            'code' => $shop->code,
            'country' => '',
            'status' => ShopStatus::ACTIVE->value,
        ])
        ->assertRedirect(route('shops.edit', $shop))
        ->assertSessionHasErrors('country');

    expect($shop->fresh()->country)->toBe('Kenya');
});

test('the create form shows the country error next to the field', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['shops.create', 'shops.view']);

    $this->actingAs($user)
        ->followingRedirects()
        ->from(route('shops.create'))
        ->post(route('shops.store'), ['name' => 'X', 'code' => 'X-1', 'country' => '', 'status' => ShopStatus::ACTIVE->value])
        ->assertOk()
        ->assertSee('The country is required.');
});

test('the api fills in a missing code and country instead of failing', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo('shops.create');

    $response = $this->actingAs($user)
        ->postJson('/api/shops', ['name' => 'App Shop', 'country' => null])
        ->assertCreated();

    $shop = Shop::where('name', 'App Shop')->sole();

    expect($shop->country)->toBe('Kenya')
        ->and($shop->code)->toStartWith('SHOP-')
        ->and($response->json('data.code'))->toBe($shop->code);
});

test('the api keeps the country when an update sends null', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['shops.update', 'shops.view']);
    $shop = Shop::factory()->create(['country' => 'Uganda']);

    $this->actingAs($user)->putJson("/api/shops/{$shop->uuid}", ['country' => null])->assertOk();

    expect($shop->fresh()->country)->toBe('Uganda');
});

test('the api refuses shop changes without the permission', function () {
    $cashier = staffUser();
    $shop = $cashier->shops()->first();

    $this->actingAs($cashier);

    $this->postJson('/api/shops', ['name' => 'Sneaky'])->assertForbidden();
    $this->putJson("/api/shops/{$shop->uuid}", ['name' => 'Renamed'])->assertForbidden();
    $this->deleteJson("/api/shops/{$shop->uuid}")->assertForbidden();

    // Reading their own shop still works: the app needs it at the till
    $this->getJson("/api/shops/{$shop->uuid}")->assertOk();

    expect($shop->fresh()->trashed())->toBeFalse();
});

test('two businesses may use the same shop code', function () {
    // The owner first: factory users join the first business that exists
    $user = User::factory()->owner()->create();
    $user->givePermissionTo('shops.create');

    $other = Business::factory()->create();
    Shop::factory()->create(['business_id' => $other->id, 'code' => 'MAIN']);

    $this->actingAs($user)->post(route('shops.store'), [
        'name' => 'Our Main Shop',
        'code' => 'MAIN',
        'country' => 'Kenya',
        'status' => ShopStatus::ACTIVE->value,
    ])->assertSessionHasNoErrors();

    expect(Shop::withoutGlobalScopes()->where('code', 'MAIN')->count())->toBe(2);
});

test('a shop code must still be unique within a business, with a form error', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['shops.create', 'shops.update']);
    $existing = Shop::factory()->create(['code' => 'MAIN']);
    $second = Shop::factory()->create(['code' => 'BRANCH']);

    $this->actingAs($user)
        ->post(route('shops.store'), ['name' => 'Dup', 'code' => 'MAIN', 'country' => 'Kenya', 'status' => ShopStatus::ACTIVE->value])
        ->assertSessionHasErrors(['code' => 'This shop code is already in use.']);

    $this->put(route('shops.update', $second), ['name' => $second->name, 'code' => 'MAIN', 'country' => 'Kenya', 'status' => ShopStatus::ACTIVE->value])
        ->assertSessionHasErrors('code');

    // Keeping its own code on edit is not a clash
    $this->put(route('shops.update', $existing), ['name' => 'Renamed', 'code' => 'MAIN', 'country' => 'Kenya', 'status' => ShopStatus::ACTIVE->value])
        ->assertSessionHasNoErrors();
});

test('shops with the same code in different businesses issue different invoice numbers', function () {
    $alphaShop = Shop::factory()->create(['code' => 'MAIN', 'business_id' => Business::factory()->create()->id]);
    $bravoShop = Shop::factory()->create(['code' => 'MAIN', 'business_id' => Business::factory()->create()->id]);

    $invoices = app(InvoiceNumberService::class);
    $alphaNumber = $invoices->next($alphaShop);
    $bravoNumber = $invoices->next($bravoShop);
    $year = now()->format('Y');

    expect($alphaShop->invoice_code)->toMatch('/^[A-HJ-NP-Z2-9]{3}$/')
        ->and($alphaShop->invoice_code)->not->toBe($bravoShop->invoice_code)
        ->and($alphaNumber)->toBe("INV-MAIN-{$alphaShop->invoice_code}-{$year}-000001")
        ->and($bravoNumber)->toBe("INV-MAIN-{$bravoShop->invoice_code}-{$year}-000001");
});

test('invoice numbers are unique system-wide in the database', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    Sale::factory()->create(['shop_id' => $shopA->id, 'invoice_number' => 'INV-DUP-1']);

    expect(fn () => Sale::factory()->create(['shop_id' => $shopB->id, 'invoice_number' => 'INV-DUP-1']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('a shop from before invoice codes keeps its format until its code changes', function () {
    $legacy = Shop::factory()->create(['code' => 'OLD']);
    $legacy->forceFill(['invoice_code' => null])->saveQuietly();
    $year = now()->format('Y');

    expect(app(InvoiceNumberService::class)->next($legacy->fresh()))->toBe("INV-OLD-{$year}-000001");

    $legacy->fresh()->update(['code' => 'MAIN']);

    expect($legacy->fresh()->invoice_code)->not->toBeNull();
});

test('a code change picks a new invoice code if the old one is taken under the new code', function () {
    $main = Shop::factory()->create(['code' => 'MAIN', 'business_id' => Business::factory()->create()->id]);
    $other = Shop::factory()->create(['code' => 'BRANCH', 'business_id' => Business::factory()->create()->id]);
    $other->forceFill(['invoice_code' => $main->invoice_code])->saveQuietly();

    $other->fresh()->update(['code' => 'MAIN']);

    expect($other->fresh()->invoice_code)->not->toBe($main->invoice_code);
});
