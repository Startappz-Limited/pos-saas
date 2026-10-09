<?php

use App\Enums\TaxClass;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\Services\VatReportService;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['reports.view', 'reports.export', 'reports.full-access'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->shop = Shop::factory()->create(['vat_registered' => true, 'tax_pin' => 'P051234567X']);
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);
    $this->user->givePermissionTo(['reports.view', 'reports.export']);
});

function vatSale(Shop $shop, array $lines, array $saleAttributes = []): Sale
{
    $sale = Sale::factory()->create(array_merge([
        'shop_id' => $shop->id,
        'status' => 'completed',
        'completed_at' => now()->subDay(),
        'tax_inclusive' => true,
    ], $saleAttributes));

    foreach ($lines as $line) {
        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'shop_id' => $shop->id,
            'product_id' => Product::factory()->create(['shop_id' => $shop->id])->id,
            'quantity' => 1,
            'unit_price' => $line['charged'],
            'line_total' => $line['charged'],
            'tax_class' => $line['class'],
            'tax_rate' => $line['rate'],
            'tax_amount' => $line['tax'],
            'taxable_amount' => $line['charged'] - $line['tax'],
            'unit_cost' => 0,
            'total_cost' => 0,
            'profit' => 0,
            'profit_margin' => 0,
            'status' => 'completed',
        ]);
    }

    return $sale;
}

describe('the VAT summary', function () {
    it('groups output tax by class and rate', function () {
        vatSale($this->shop, [
            ['charged' => 1160, 'class' => TaxClass::Standard, 'rate' => 16, 'tax' => 160],
            ['charged' => 500, 'class' => TaxClass::ZeroRated, 'rate' => 0, 'tax' => 0],
        ]);

        $summary = app(VatReportService::class)->summary(
            $this->shop,
            now()->subDays(5),
            now(),
        );

        expect($summary['bands'])->toHaveCount(2)
            ->and($summary['totals']['output_tax'])->toBe(160.0);
    });

    it('separates taxable supplies from exempt ones', function () {
        vatSale($this->shop, [
            ['charged' => 1160, 'class' => TaxClass::Standard, 'rate' => 16, 'tax' => 160],
            ['charged' => 500, 'class' => TaxClass::ZeroRated, 'rate' => 0, 'tax' => 0],
            ['charged' => 300, 'class' => TaxClass::Exempt, 'rate' => 0, 'tax' => 0],
        ]);

        $summary = app(VatReportService::class)->summary($this->shop, now()->subDays(5), now());

        // Zero rated is a taxable supply at 0%; exempt is not a taxable supply.
        expect($summary['totals']['taxable_supplies'])->toBe(1500.0)
            ->and($summary['totals']['exempt_supplies'])->toBe(300.0);
    });

    it('ignores sales outside the period', function () {
        vatSale($this->shop, [
            ['charged' => 1160, 'class' => TaxClass::Standard, 'rate' => 16, 'tax' => 160],
        ], ['completed_at' => now()->subMonths(3)]);

        $summary = app(VatReportService::class)->summary($this->shop, now()->subDays(5), now());

        expect($summary['totals']['output_tax'])->toBe(0.0);
    });

    it('ignores voided sales', function () {
        vatSale($this->shop, [
            ['charged' => 1160, 'class' => TaxClass::Standard, 'rate' => 16, 'tax' => 160],
        ], ['status' => 'voided']);

        $summary = app(VatReportService::class)->summary($this->shop, now()->subDays(5), now());

        expect($summary['totals']['output_tax'])->toBe(0.0);
    });

    it('flags sales that carry no tax class so the report never reads as complete when it is not', function () {
        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);

        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'shop_id' => $this->shop->id,
            'product_id' => Product::factory()->create(['shop_id' => $this->shop->id])->id,
            'quantity' => 1,
            'unit_price' => 900,
            'line_total' => 900,
            'tax_class' => null,
            'unit_cost' => 0,
            'total_cost' => 0,
            'profit' => 0,
            'profit_margin' => 0,
            'status' => 'completed',
        ]);

        $summary = app(VatReportService::class)->summary($this->shop, now()->subDays(5), now());

        expect($summary['unclassified']['invoices'])->toBe(1)
            ->and($summary['unclassified']['amount'])->toBe(900.0);
    });

    it('does not leak another shop\'s sales', function () {
        $other = Shop::factory()->create(['vat_registered' => true]);
        vatSale($other, [
            ['charged' => 1160, 'class' => TaxClass::Standard, 'rate' => 16, 'tax' => 160],
        ]);

        $summary = app(VatReportService::class)->summary($this->shop, now()->subDays(5), now());

        expect($summary['totals']['output_tax'])->toBe(0.0);
    });
});

describe('the VAT report screen', function () {
    it('is reachable by a user with reports.view', function () {
        $this->actingAs($this->user)
            ->get(route('reports.vat'))
            ->assertOk()
            ->assertSee('VAT');
    });

    it('is refused without the permission', function () {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('reports.vat'))
            ->assertForbidden();
    });

    it('refuses a shop the user cannot see', function () {
        $other = Shop::factory()->create();

        $this->actingAs($this->user)
            ->get(route('reports.vat', ['shop_id' => $other->id]))
            ->assertForbidden();
    });

    it('exports a CSV', function () {
        vatSale($this->shop, [
            ['charged' => 1160, 'class' => TaxClass::Standard, 'rate' => 16, 'tax' => 160],
        ]);

        $this->actingAs($this->user)
            ->get(route('reports.vat.export', [
                'shop_id' => $this->shop->id,
                'start_date' => now()->subDays(5)->toDateString(),
                'end_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    });
});
