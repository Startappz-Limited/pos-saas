<?php

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Services\InvoiceNumberService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 * Tills and the web never share an invoice series (stage 10, step 5d): a till
 * numbers in its register's series (INV-{shop}-{register}-{year}-{n}); the web
 * POS, the flat API, online orders and abandoned carts take the shop's web
 * series (INV-{shop}-W-{year}-{n}). W is never a register code.
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-04 10:00:00'));
    $this->numbers = app(InvoiceNumberService::class);
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->r1 = Register::defaultFor($this->shop->id);
    $this->r2 = Register::create(['shop_id' => $this->shop->id, 'name' => 'Till 2', 'code' => 'R2']);
});

it('records two tills of one shop issuing sequence number 145', function () {
    $this->numbers->adopt($this->shop->id, $this->r1->id, 'INV-NRB-R1-2026-000145', now());
    $this->numbers->adopt($this->shop->id, $this->r2->id, 'INV-NRB-R2-2026-000145', now());

    expect($this->numbers->next($this->shop, $this->r1))->toBe('INV-NRB-R1-2026-000146')
        ->and($this->numbers->next($this->shop, $this->r2))->toBe('INV-NRB-R2-2026-000146');
});

it('keeps web sales in their own series, so a web sale and a till sale at the same moment can\'t collide', function () {
    $web = $this->numbers->nextWeb($this->shop);
    $till = $this->numbers->next($this->shop, $this->r1);

    expect($web)->toBe('INV-NRB-W-2026-000001')
        ->and($till)->toBe('INV-NRB-R1-2026-000001')
        ->and($this->numbers->nextWeb($this->shop))->toBe('INV-NRB-W-2026-000002')
        ->and($this->numbers->isWebNumber($this->shop->id, $web))->toBeTrue()
        ->and($this->numbers->isWebNumber($this->shop->id, $till))->toBeFalse();
});

it('takes the year from the shop\'s local date, not the server\'s', function () {
    // 21:30 UTC on 31 December is 00:30 on 1 January in Nairobi.
    $this->travelTo(Carbon::parse('2026-12-31 21:30:00', 'UTC'));

    expect($this->numbers->nextWeb($this->shop))->toBe('INV-NRB-W-2027-000001')
        ->and($this->numbers->next($this->shop, $this->r1))->toBe('INV-NRB-R1-2027-000001')
        ->and($this->numbers->nextAdjustment($this->shop))->toBe('ADJ-NRB-2027-000001');

    // A shop with its own timezone, still on 31 December there.
    $london = Shop::factory()->create(['code' => 'LDN', 'timezone' => 'Europe/London']);
    expect($this->numbers->nextWeb($london))->toBe('INV-LDN-W-2026-000001');
});

it('never lets a till adopt a web-series number', function () {
    expect(fn () => $this->numbers->adopt($this->shop->id, $this->r1->id, 'INV-NRB-W-2026-000007', now()))->toThrow(InvalidArgumentException::class);
});

it('reserves W: a register\'s code is R and digits', function () {
    expect(fn () => Register::create(['shop_id' => $this->shop->id, 'name' => 'Web', 'code' => 'W']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Register::create(['shop_id' => $this->shop->id, 'name' => 'Odd', 'code' => 'TILL']))->toThrow(InvalidArgumentException::class);
});

it('numbers a flat API sale in the web series while an old app\'s till keeps its register series, and leaves existing numbers alone', function () {
    seedRolesAndPermissions();
    $existing = Sale::factory()->create(['shop_id' => $this->shop->id, 'invoice_number' => 'INV-NRB-R1-2026-000044']);
    $this->numbers->adopt($this->shop->id, $this->r1->id, 'INV-NRB-R1-2026-000044', now());

    // A sale through the flat API (what the app in shops uses) is numbered by the server: web series.
    $user = User::factory()->create();
    $user->assignRole(ownerRole());
    $user->shops()->attach($this->shop);
    $product = Product::factory()->create(['shop_id' => $this->shop->id]);
    CashRegister::factory()->create(['shop_id' => $this->shop->id, 'register_id' => $this->r1->id, 'user_id' => $user->id]);
    Sanctum::actingAs($user, ['*']);
    $number = $this->postJson('/api/sales', [
        'shop_id' => $this->shop->id, 'source_id' => DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id'),
        'delivery_location' => 'In store', 'walk_in_customer_name' => 'Walk-in', 'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash', 'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 100]],
    ])->assertCreated()->json('data.invoice_number');

    expect($number)->toBe('INV-NRB-W-2026-000001')
        ->and($this->numbers->next($this->shop, $this->r1))->toBe('INV-NRB-R1-2026-000045')
        ->and($existing->fresh()->invoice_number)->toBe('INV-NRB-R1-2026-000044');

    Artisan::call('invoices:report-series', ['--business' => testBusiness()->id]);
    expect(Artisan::output())->toContain('INV-NRB-R1-2026')->toContain('INV-NRB-W-2026')->toContain('Nothing was changed.');
});
