<?php

use App\Enums\ReturnStatus;
use App\Enums\StockMovementType;
use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\User;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/*
 * The Void button on the web sale page (stage 10, step 5a): shown only when
 * SalePolicy::void passes, with until when; otherwise why not, in one line, or
 * nothing for someone without the permission. Its confirmation names the
 * items, the shop and the invoice, and needs a reason.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-04 10:00:00', 'Africa/Nairobi'));
    $this->shop = Shop::factory()->create(['name' => 'Westlands']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->owner->shops()->attach($this->shop);
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0]);
    app(StockLedger::class)->receive($this->shop->id, $product->defaultVariation, 10, 40, StockMovementType::INTAKE);
    $this->session = CashRegister::factory()->create(['shop_id' => $this->shop->id, 'user_id' => $this->owner->id]);
    $this->sale = Sale::factory()->create([
        'shop_id' => $this->shop->id, 'register_id' => $this->session->id, 'status' => 'completed', 'completed_at' => now(),
        'payment_method' => 'cash', 'total_amount' => 400, 'paid_amount' => 400, 'tax_amount' => 0, 'invoice_number' => 'INV-WST-W-2026-000007',
    ]);
    $line = SaleItem::create([
        'uuid' => (string) Str::uuid(), 'sale_id' => $this->sale->id, 'product_id' => $product->id, 'shop_id' => $this->shop->id,
        'quantity' => 4, 'unit_price' => 100, 'line_total' => 400, 'taxable_amount' => 400, 'status' => 'completed',
    ]);
    app(StockLedger::class)->sellItem($line, $product->defaultVariation);
    $this->page = fn (User $user) => $this->actingAs($user)->get(route('sales.show', $this->sale));
    $this->setWindow = fn (string|int $value) => testBusiness()->update(['settings' => array_replace_recursive(testBusiness()->settings ?? [], ['sales' => ['void_window' => $value]])]);
});

it('shows the button to a holder within the window, with until when, and a confirmation naming the items, shop and invoice', function () {
    ($this->page)($this->owner)->assertOk()
        ->assertSee('Void sale')
        ->assertSee('Can be voided until the register session closes (11:59 pm today at the latest).')
        ->assertSee('Void this sale?')
        ->assertSee('All 4 items go back into stock in Westlands.')
        ->assertSee('The invoice number INV-WST-W-2026-000007 stays in the records, marked Voided.')
        ->assertSee('Cash 400.00 comes back out of its register session', false)
        ->assertSee('name="void_reason"', false);
});

it('hides the button past the window and says why', function (string|int $window, string $after, string $why) {
    ($this->setWindow)($window);
    $this->travelTo(Carbon::parse($after, 'Africa/Nairobi'));

    ($this->page)($this->owner)->assertOk()->assertDontSee('id="void-sale-button"', false)->assertSee($why);
})->with([
    'end of day' => ['end_of_day', '2026-11-05 08:00:00', 'This sale can only be voided on the day it was made. Use a return instead.'],
    'minutes' => [30, '2026-11-04 12:00:00', 'Voiding closed 1 hour ago (limit 30 minutes). Use a return instead.'],
]);

it('says how long is left with a limit in minutes, and nothing with no limit', function () {
    ($this->setWindow)(60);
    $this->travel(20)->minutes();
    ($this->page)($this->owner)->assertSee('Can be voided for another 40 minutes.');

    ($this->setWindow)('none');
    ($this->page)($this->owner)->assertSee('id="void-sale-button"', false)->assertDontSee('Can be voided');
});

it('hides it for a sale with a return that isn\'t rejected, shows it once the only return is rejected, and hides it with no word for someone without the permission', function () {
    $return = SaleReturn::factory()->create(['sale_id' => $this->sale->id, 'shop_id' => $this->shop->id, 'status' => ReturnStatus::PENDING]);
    ($this->page)($this->owner)->assertDontSee('id="void-sale-button"', false)->assertSee('This sale has a return, so it can\'t be voided.');

    $return->update(['status' => ReturnStatus::REJECTED]);
    ($this->page)($this->owner)->assertSee('id="void-sale-button"', false);

    $manager = User::factory()->create();
    $manager->assignRole('manager');
    $manager->shops()->attach($this->shop);
    ($this->page)($manager)->assertOk()->assertDontSee('id="void-sale-button"', false)->assertDontSee('Can be voided')->assertDontSee('id="void-unavailable"', false);
});

it('shows the error under the reason when it is missing, and the Voided badge after a void', function () {
    $this->actingAs($this->owner)->followingRedirects()->from(route('sales.show', $this->sale))
        ->post(route('sales.void', $this->sale), ['void_reason' => ''])
        ->assertOk()->assertSee('id="void_reason-error"', false)->assertSee('new bootstrap.Modal(modal).show()', false);

    $this->actingAs($this->owner)->followingRedirects()->from(route('sales.show', $this->sale))
        ->post(route('sales.void', $this->sale), ['void_reason' => 'Rang it twice'])
        ->assertOk()->assertSee('Sale voided. 4 items are back in stock.')->assertSee('Voided')->assertSee('Reason: Rang it twice');
});
