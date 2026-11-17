<?php

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Register;
use App\Models\Shop;
use App\Models\UnassignedBarcode;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/*
 * A product barcode waiting for a unit (stage 11, step 7): an owner assigns it to one of the
 * product's units or drops it with a reason, both audited; nothing sends it until then.
 */

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-17 10:00:00', 'Africa/Nairobi'));
    seedRolesAndPermissions();
    migrateAuditDatabase();
    $this->shop = Shop::factory()->create();
    $this->user = assignToNewShops(User::factory()->create());
    $this->user->assignRole(ownerRole());
    $this->actingAs($this->user);
    $this->tee = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => true, 'barcode' => 'TEE-ALL']);
    $this->unit = ProductUnit::factory()->create([
        'variation_id' => $this->tee->defaultVariation->id, 'name' => 'Pack of 2', 'factor' => '2.000', 'precision' => 0,
    ]);
    $this->waiting = UnassignedBarcode::create(['business_id' => testBusiness()->id, 'product_id' => $this->tee->id, 'barcode' => 'TEE-ALL']);
});

it('shows the waiting barcode on the product page, with its warning', function () {
    $this->get(route('products.edit', $this->tee))->assertOk()
        ->assertSee('Barcode to assign')
        ->assertSee('TEE-ALL')
        ->assertSee('Scanning this barcode won');
});

it('gives the waiting barcode to the chosen unit, removes it from the waiting list, and audits it', function () {
    $this->post(route('products.barcodes.assign', [$this->tee, $this->waiting]), ['unit' => $this->unit->uuid])
        ->assertSessionHas('success');

    expect($this->unit->fresh()->barcode)->toBe('TEE-ALL')
        ->and(UnassignedBarcode::query()->whereKey($this->waiting->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->get()->contains(fn ($row) => in_array('barcode', $row->tags ?? [], true) && in_array('assigned', $row->tags ?? [], true)))->toBeTrue();
});

it('drops the waiting barcode with a reason, and audits the reason', function () {
    $this->post(route('products.barcodes.drop', [$this->tee, $this->waiting]), ['reason' => 'Printed on the wrong label'])
        ->assertSessionHas('success');

    expect(UnassignedBarcode::query()->whereKey($this->waiting->id)->exists())->toBeFalse();
    $row = AuditLog::query()->get()->first(fn ($row) => in_array('dropped', $row->tags ?? [], true));
    expect($row)->not->toBeNull()
        ->and(json_encode($row->new_values))->toContain('Printed on the wrong label');
});

it('refuses a drop without a reason, and leaves the barcode waiting', function () {
    $this->post(route('products.barcodes.drop', [$this->tee, $this->waiting]), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect(UnassignedBarcode::query()->whereKey($this->waiting->id)->exists())->toBeTrue();
});

it('refuses a unit of another product, and a barcode another unit already holds', function () {
    $other = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]);
    $foreign = ProductUnit::factory()->create(['variation_id' => $other->defaultVariation->id, 'name' => 'Box', 'factor' => '6.000', 'precision' => 0]);

    $this->post(route('products.barcodes.assign', [$this->tee, $this->waiting]), ['unit' => $foreign->uuid])
        ->assertSessionHas('error');
    expect($foreign->fresh()->barcode)->toBeNull();

    ProductUnit::factory()->create(['variation_id' => $other->defaultVariation->id, 'name' => 'Single', 'factor' => '1.000', 'precision' => 0, 'barcode' => 'TEE-ALL']);
    $this->post(route('products.barcodes.assign', [$this->tee, $this->waiting]), ['unit' => $this->unit->uuid])
        ->assertSessionHas('error');
    expect($this->unit->fresh()->barcode)->toBeNull()
        ->and(UnassignedBarcode::query()->whereKey($this->waiting->id)->exists())->toBeTrue();
});

it('keeps another business\'s product and waiting barcode out of reach', function () {
    $two = TwoBusinesses::create();
    $theirs = app(TenantContext::class)->run($two->b->business, fn () => UnassignedBarcode::create([
        'business_id' => $two->b->business->id, 'product_id' => $two->b->product->id, 'barcode' => 'THEIRS',
    ]));

    $this->post(route('products.barcodes.drop', [$this->tee, $theirs]), ['reason' => 'x'])->assertNotFound();
    expect(UnassignedBarcode::withoutGlobalScopes()->whereKey($theirs->id)->exists())->toBeTrue();
});

it('sends no waiting product barcode on the pull, and the variation\'s barcode still', function () {
    $register = Register::defaultFor($this->shop->id);
    $token = Till::token($this->user, $register);
    $this->travel(5)->seconds();
    Till::freshRequest();

    $products = collect(test()->withToken($token)->getJson('/api/v1/sync/pull?'.http_build_query(['since' => null]))->assertOk()->json('data.products'));
    $row = $products->firstWhere('uuid', $this->tee->uuid);

    expect($row)->not->toBeNull()
        ->and($row['barcode'])->toBeNull();

});

it('answers no waiting product barcode on the flat API either', function () {
    Sanctum::actingAs($this->user, ['*']);

    expect($this->getJson("/api/products/{$this->tee->getRouteKey()}")->assertOk()->json('data.barcode'))->toBeNull();
});
