<?php

use App\Enums\TaxClass;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * Cover for the VAT engine and the profit bug it sits on top of.
 *
 * Before this, `tax_amount` was whatever the till or the mobile app posted, and
 * profit was `total_amount - total_cost` — so the VAT owed to KRA was booked as
 * margin on every taxed sale.
 */
beforeEach(function () {
    Permission::findOrCreate('sales.full-access');

    config([
        'tax.enabled' => true,
        'tax.rates.standard' => 16.0,
        'tax.default_class' => 'standard',
        'tax.prices_include_tax' => true,
        'tax.fees_taxable' => false,
    ]);

    $this->shop = Shop::factory()->create(['code' => 'NRB', 'vat_registered' => false]);
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);
    $this->user->givePermissionTo('sales.full-access');
    $this->source = SaleSource::create(['name' => 'Counter', 'is_active' => true, 'sort_order' => 1]);
});

function vatProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge([
        'shop_id' => Shop::first()->id,
        'selling_price' => 1160,
        'cost_price' => 600,
        'stock_quantity' => 100,
        'status' => 'active',
    ], $attributes));
}

function vatSalePayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'source_id' => SaleSource::first()->id,
        'delivery_location' => 'Front counter',
        'walk_in_customer_name' => 'Walk In',
        'walk_in_customer_phone' => '0712345678',
        'payment_method' => 'cash',
        'items' => [
            ['product_id' => $product->id, 'quantity' => 1, 'price' => 1160],
        ],
    ], $overrides);
}

describe('shops that are not VAT registered', function () {
    it('keeps storing the posted tax amount, unchanged', function () {
        $product = vatProduct();

        $this->actingAs($this->user)
            ->post(route('sales.store'), vatSalePayload($product, ['tax_amount' => 40]))
            ->assertRedirect();

        $sale = Sale::firstOrFail();

        expect((float) $sale->tax_amount)->toBe(40.0)
            ->and($sale->tax_inclusive)->toBeFalse()
            ->and((float) $sale->total_amount)->toBe(1200.0);
    });

    it('still keeps VAT out of profit', function () {
        $product = vatProduct();

        $this->actingAs($this->user)
            ->post(route('sales.store'), vatSalePayload($product, ['tax_amount' => 40]))
            ->assertRedirect();

        // 1160 goods + 40 tax = 1200 paid; 600 cost. Profit is 1160 - 600 = 560,
        // not the 600 the old `total_amount - total_cost` produced.
        expect((float) Sale::firstOrFail()->total_profit)->toBe(560.0);
    });
});

describe('VAT registered shops, inclusive pricing', function () {
    beforeEach(function () {
        $this->shop->update(['vat_registered' => true]);
    });

    it('extracts 16% VAT from the shelf price without changing what the customer pays', function () {
        $product = vatProduct();

        $this->actingAs($this->user)
            ->post(route('sales.store'), vatSalePayload($product))
            ->assertRedirect();

        $sale = Sale::firstOrFail();

        expect((float) $sale->total_amount)->toBe(1160.0)
            ->and((float) $sale->tax_amount)->toBe(160.0)
            ->and((float) $sale->taxable_amount)->toBe(1000.0)
            ->and($sale->tax_inclusive)->toBeTrue();
    });

    it('ignores a tax amount posted by the client', function () {
        $product = vatProduct();

        $this->actingAs($this->user)
            ->post(route('sales.store'), vatSalePayload($product, ['tax_amount' => 9999]))
            ->assertRedirect();

        expect((float) Sale::firstOrFail()->tax_amount)->toBe(160.0);
    });

    it('excludes VAT from profit', function () {
        $product = vatProduct();

        $this->actingAs($this->user)
            ->post(route('sales.store'), vatSalePayload($product))
            ->assertRedirect();

        // Net revenue 1000, cost 600.
        expect((float) Sale::firstOrFail()->total_profit)->toBe(400.0);
    });

    it('records the tax class, rate and amount on each line', function () {
        $product = vatProduct();

        $this->actingAs($this->user)
            ->post(route('sales.store'), vatSalePayload($product))
            ->assertRedirect();

        $item = SaleItem::firstOrFail();

        expect($item->tax_class)->toBe(TaxClass::Standard)
            ->and((float) $item->tax_rate)->toBe(16.0)
            ->and((float) $item->tax_amount)->toBe(160.0)
            ->and((float) $item->taxable_amount)->toBe(1000.0);
    });

    it('stores a per-class breakdown for the invoice and the VAT return', function () {
        $standard = vatProduct(['tax_class' => TaxClass::Standard]);
        $exempt = vatProduct(['sku' => 'EX-1', 'tax_class' => TaxClass::Exempt]);

        $this->actingAs($this->user)
            ->post(route('sales.store'), vatSalePayload($standard, [
                'items' => [
                    ['product_id' => $standard->id, 'quantity' => 1, 'price' => 1160],
                    ['product_id' => $exempt->id, 'quantity' => 1, 'price' => 500],
                ],
            ]))
            ->assertRedirect();

        $sale = Sale::firstOrFail();

        expect($sale->tax_breakdown)->toHaveCount(2)
            ->and((float) $sale->tax_amount)->toBe(160.0)
            ->and((float) $sale->total_amount)->toBe(1660.0);
    });

    it('charges no VAT on a zero rated product', function () {
        $product = vatProduct(['tax_class' => TaxClass::ZeroRated]);

        $this->actingAs($this->user)
            ->post(route('sales.store'), vatSalePayload($product))
            ->assertRedirect();

        $sale = Sale::firstOrFail();

        expect((float) $sale->tax_amount)->toBe(0.0)
            ->and((float) $sale->total_amount)->toBe(1160.0);
    });
});

describe('VAT registered shops, exclusive pricing', function () {
    beforeEach(function () {
        $this->shop->update([
            'vat_registered' => true,
            'settings' => ['tax' => ['prices_include_tax' => false]],
        ]);
    });

    it('adds VAT on top of the quoted net price', function () {
        $product = vatProduct();

        $this->actingAs($this->user)
            ->post(route('sales.store'), vatSalePayload($product, [
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 1000]],
            ]))
            ->assertRedirect();

        $sale = Sale::firstOrFail();

        expect((float) $sale->subtotal)->toBe(1000.0)
            ->and((float) $sale->tax_amount)->toBe(160.0)
            ->and((float) $sale->total_amount)->toBe(1160.0)
            ->and($sale->tax_inclusive)->toBeFalse();
    });
});

describe('the API path', function () {
    it('computes VAT identically to the web till', function () {
        $this->shop->update(['vat_registered' => true]);
        $product = vatProduct();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sales', vatSalePayload($product, ['tax_amount' => 9999]))
            ->assertCreated();

        $sale = Sale::firstOrFail();

        expect((float) $sale->tax_amount)->toBe(160.0)
            ->and((float) $sale->total_amount)->toBe(1160.0)
            ->and((float) $sale->total_profit)->toBe(400.0);
    });
});

describe('invoice numbering', function () {
    it('issues sequential numbers rather than random strings', function () {
        $product = vatProduct();

        foreach (range(1, 3) as $ignored) {
            $this->actingAs($this->user)
                ->post(route('sales.store'), vatSalePayload($product))
                ->assertRedirect();
        }

        $numbers = Sale::orderBy('id')->pluck('invoice_number')->all();

        expect($numbers)->toBe([
            'INV-NRB-'.now()->format('Y').'-000001',
            'INV-NRB-'.now()->format('Y').'-000002',
            'INV-NRB-'.now()->format('Y').'-000003',
        ]);
    });

    it('keeps each shop on its own series', function () {
        $other = Shop::factory()->create(['code' => 'MSA']);
        $this->user->shops()->attach($other);

        $productA = vatProduct();
        $productB = Product::factory()->create([
            'shop_id' => $other->id,
            'sku' => 'MSA-1',
            'selling_price' => 1160,
            'cost_price' => 600,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sales', vatSalePayload($productA))
            ->assertCreated();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sales', vatSalePayload($productB))
            ->assertCreated();

        $year = now()->format('Y');

        expect(Sale::orderBy('id')->pluck('invoice_number')->all())
            ->toBe(["INV-NRB-{$year}-000001", "INV-MSA-{$year}-000001"]);
    });
});

describe('customer phone normalisation', function () {
    it('stores a canonical MSISDN alongside what the cashier typed', function () {
        $customer = Customer::factory()->forShop($this->shop)->create(['phone' => '0712 345 678']);

        expect($customer->fresh()->phone)->toBe('0712 345 678')
            ->and($customer->fresh()->phone_normalized)->toBe('254712345678');
    });

    it('matches a customer regardless of how the number was written', function () {
        Customer::factory()->forShop($this->shop)->create(['phone' => '+254712345678']);

        expect(Customer::wherePhone('0712345678')->exists())->toBeTrue()
            ->and(Customer::wherePhone('712345678')->exists())->toBeTrue()
            ->and(Customer::wherePhone('0722000111')->exists())->toBeFalse();
    });
});
