<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\Supplier;
use App\Rules\UniqueForTenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Tests\Support\TwoBusinesses;

/**
 * Codes users type, and invoice numbers, are unique per business; a
 * soft-deleted record's code can be reused (stage 1a acceptance).
 */
beforeEach(function () {
    $this->fixture = TwoBusinesses::create();
    app(TenantContext::class)->set($this->fixture->a->business);
});

describe('the database', function () {
    it('lets both businesses use the same codes', function () {
        // The fixture gives A and B the same shop codes, customer, product,
        // supplier codes and invoice number.
        $counts = app(TenantContext::class)->bypass(fn () => [
            Shop::where('code', 'MAIN')->count(),
            Customer::where('code', 'CUST-1')->count(),
            Product::where('sku', 'SKU-1')->count(),
            Supplier::where('code', 'SUP-1')->count(),
            Sale::where('invoice_number', 'INV-1')->count(),
        ]);

        expect($counts)->toBe([2, 2, 2, 2, 2]);
    });

    it('refuses a duplicate code within one business', function (string $key) {
        $shop = $this->fixture->a->shops[0];
        $duplicate = match ($key) {
            'shop code' => fn () => Shop::factory()->create(['code' => 'MAIN']),
            'product SKU' => fn () => Product::factory()->create(['sku' => 'SKU-1']),
            'variation SKU' => fn () => ProductVariation::factory()->count(2)->create(['sku' => 'VAR-1']),
            'customer code' => fn () => Customer::factory()->forShop($shop)->create(['code' => 'CUST-1']),
            'supplier code' => fn () => Supplier::factory()->create(['code' => 'SUP-1']),
            'sale source name' => fn () => SaleSource::create(['name' => 'Walk-in']),
            'invoice number' => fn () => Sale::factory()->create(['shop_id' => $shop->id, 'invoice_number' => 'INV-1']),
        };

        expect($duplicate)->toThrow(QueryException::class);
    })->with(['shop code', 'product SKU', 'variation SKU', 'customer code', 'supplier code', 'sale source name', 'invoice number']);

    it('lets a soft-deleted code be reused', function (string $key) {
        $shop = $this->fixture->a->shops[0];
        $make = match ($key) {
            'shop code' => fn () => Shop::factory()->create(['code' => 'REUSED']),
            'product SKU, slug and barcode' => fn () => Product::factory()->create(['sku' => 'REUSED', 'slug' => 'reused', 'barcode' => '5000000000001']),
            'customer code' => fn () => Customer::factory()->forShop($shop)->create(['code' => 'REUSED']),
            'supplier code' => fn () => Supplier::factory()->create(['code' => 'REUSED']),
            'category slug' => fn () => Category::factory()->create(['slug' => 'reused']),
        };

        $make()->delete();

        expect($make()->exists)->toBeTrue();
    })->with(['shop code', 'product SKU, slug and barcode', 'customer code', 'supplier code', 'category slug']);
});

describe('validation', function () {
    function uniqueSupplierCode(string $code, ?Supplier $ignore = null): bool
    {
        return Validator::make(['code' => $code], ['code' => [(new UniqueForTenant(Supplier::class, 'code'))->ignore($ignore)]])->passes();
    }

    it('accepts a code another business uses and refuses one this business uses', function () {
        $onlyInB = app(TenantContext::class)->run($this->fixture->b->business, fn () => Supplier::factory()->create(['code' => 'ONLY-B']));

        expect(uniqueSupplierCode('ONLY-B'))->toBeTrue()
            ->and(uniqueSupplierCode('SUP-1'))->toBeFalse()
            ->and($onlyInB->business_id)->toBe($this->fixture->b->business->id);
    });

    it('accepts a soft-deleted code and the record being updated', function () {
        Supplier::factory()->create(['code' => 'GONE'])->delete();

        expect(uniqueSupplierCode('GONE'))->toBeTrue()
            ->and(uniqueSupplierCode('SUP-1', $this->fixture->a->supplier))->toBeTrue();
    });

    it('lets a supplier be created with a code another business uses', function () {
        $owner = $this->fixture->a->owner;
        Permission::findOrCreate('suppliers.create');
        $owner->givePermissionTo('suppliers.create');

        app(TenantContext::class)->run($this->fixture->b->business, fn () => Supplier::factory()->create(['code' => 'SHARED']));

        $this->actingAs($owner)
            ->post(route('suppliers.store'), ['name' => 'Shared Code Ltd', 'code' => 'SHARED', 'status' => 'active'])
            ->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->post(route('suppliers.store'), ['name' => 'Taken Code Ltd', 'code' => 'SUP-1', 'status' => 'active'])
            ->assertSessionHasErrors('code');
    });
});
