<?php

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use Tests\Support\TwoBusinesses;

/*
 * The units editor on a product (stage 11, step 2): an owner adds, changes and removes the units a
 * product's variations are sold in; the model's rules (one base unit, a sold unit's pack size locked)
 * are shown as messages, and nothing outside the product or the business is reachable.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->shop = Shop::factory()->create();
    $this->user = assignToNewShops(User::factory()->create());
    $this->user->assignRole(ownerRole());
    $this->actingAs($this->user);
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'name' => 'Soda']);
    $this->base = ProductUnit::query()->where('variation_id', $this->product->defaultVariation->id)->where('is_base', true)->firstOrFail();
});

it('shows the units of the product on its page', function () {
    $this->get(route('products.units.index', $this->product))->assertOk()->assertSee('Soda')->assertSee('Add unit');
});

it('adds a unit to the product\'s variation', function () {
    $this->post(route('products.units.store', $this->product), [
        'variation' => $this->product->defaultVariation->uuid, 'name' => 'Crate of 24', 'factor' => '24', 'precision' => 0, 'barcode' => 'CRATE-24', 'selling_price' => 2800,
    ])->assertSessionHas('success');

    $unit = ProductUnit::query()->where('name', 'Crate of 24')->firstOrFail();
    expect((float) $unit->factor)->toBe(24.0)
        ->and($unit->barcode)->toBe('CRATE-24')
        ->and($unit->is_base)->toBeFalse();
});

it('refuses a barcode another unit in the business already has', function () {
    ProductUnit::factory()->create(['variation_id' => $this->product->defaultVariation->id, 'name' => 'Pack', 'factor' => '6', 'precision' => 0, 'barcode' => 'TAKEN-1']);

    $this->post(route('products.units.store', $this->product), [
        'variation' => $this->product->defaultVariation->uuid, 'name' => 'Box', 'factor' => '12', 'precision' => 0, 'barcode' => 'TAKEN-1',
    ])->assertSessionHasErrors('barcode');
});

it('refuses to change the pack size of a unit that has been sold', function () {
    $pack = ProductUnit::factory()->create(['variation_id' => $this->product->defaultVariation->id, 'name' => 'Pack', 'factor' => '6', 'precision' => 0]);
    $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'created_by' => $this->user->id]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $this->product->id, 'shop_id' => $this->shop->id, 'variation_id' => $this->product->defaultVariation->id, 'unit_id' => $pack->id, 'quantity' => 1, 'base_quantity' => 6, 'unit_price' => 100, 'line_total' => 100]);

    $this->put(route('products.units.update', [$this->product, $pack]), [
        'name' => 'Pack', 'factor' => '8', 'precision' => 0,
    ])->assertSessionHas('error');

    expect((float) $pack->fresh()->factor)->toBe(6.0);
});

it('keeps the base unit: its pack size is always 1, and it can\'t be removed', function () {
    $this->put(route('products.units.update', [$this->product, $this->base]), ['name' => 'piece', 'factor' => '5', 'precision' => 0])
        ->assertSessionHas('success');
    expect((float) $this->base->fresh()->factor)->toBe(1.0);

    $this->delete(route('products.units.destroy', [$this->product, $this->base]))->assertSessionHas('error');
    expect(ProductUnit::query()->whereKey($this->base->id)->exists())->toBeTrue();
});

it('removes an unsold, non-base unit', function () {
    $extra = ProductUnit::factory()->create(['variation_id' => $this->product->defaultVariation->id, 'name' => 'Spare', 'factor' => '2', 'precision' => 0]);

    $this->delete(route('products.units.destroy', [$this->product, $extra]))->assertSessionHas('success');
    expect(ProductUnit::query()->withTrashed()->whereKey($extra->id)->value('deleted_at'))->not->toBeNull();
});

it('refuses a cashier, and a unit of another product', function () {
    $cashier = assignToNewShops(User::factory()->create());
    $cashier->assignRole(sharedRole('cashier'));
    $this->actingAs($cashier)->get(route('products.units.index', $this->product))->assertForbidden();

    $this->actingAs($this->user);

    $other = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]);
    $foreign = ProductUnit::factory()->create(['variation_id' => $other->defaultVariation->id, 'name' => 'Box', 'factor' => '6', 'precision' => 0]);
    $this->delete(route('products.units.destroy', [$this->product, $foreign]))->assertNotFound();
    expect(ProductUnit::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('keeps another business\'s product out of reach', function () {
    $two = TwoBusinesses::create();
    $this->get(route('products.units.index', $two->b->product))->assertNotFound();
});
