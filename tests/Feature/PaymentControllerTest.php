<?php

use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * PaymentController was an empty stub. It now reads `sale_payments` — the table
 * the sale flow actually writes — rather than the unused `payments` table its
 * routes originally pointed at.
 */
beforeEach(function () {
    foreach (['payments.view', 'sales.view'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->shop = Shop::factory()->create();
    $this->otherShop = Shop::factory()->create();

    $this->user = User::factory()->create();
    $this->user->shops()->sync([$this->shop->id]);
    $this->user->givePermissionTo(['payments.view', 'sales.view']);

    $this->actingAs($this->user);
});

function payment(array $attributes = [], ?Shop $shop = null): SalePayment
{
    $sale = Sale::factory()->create([
        'shop_id' => ($shop ?? test()->shop)->id,
        'status' => 'completed',
    ]);

    return SalePayment::create(array_merge([
        'sale_id' => $sale->id,
        'payment_number' => 'PAY-'.strtoupper(Str::random(8)),
        'amount' => 250.00,
        'payment_method' => 'cash',
        'paid_at' => now(),
        'received_by' => test()->user->id,
    ], $attributes));
}

// --- index ---

it('lists payments for sales in the user\'s shops', function () {
    $mine = payment();
    $theirs = payment([], test()->otherShop);

    $ids = $this->get(route('payments.index'))->assertOk()->viewData('payments')->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($theirs->id);
});

it('filters payments by method', function () {
    $cash = payment(['payment_method' => 'cash']);
    $card = payment(['payment_method' => 'card']);

    $ids = $this->get(route('payments.index', ['payment_method' => 'card']))
        ->assertOk()->viewData('payments')->pluck('id');

    expect($ids)->toContain($card->id)->and($ids)->not->toContain($cash->id);
});

it('finds a payment by its payment number', function () {
    $target = payment(['payment_number' => 'PAY-FINDME1']);
    $other = payment(['payment_number' => 'PAY-OTHER22']);

    $ids = $this->get(route('payments.index', ['search' => 'PAY-FINDME1']))
        ->assertOk()->viewData('payments')->pluck('id');

    expect($ids)->toContain($target->id)->and($ids)->not->toContain($other->id);
});

it('reports totals that exclude other shops', function () {
    payment(['amount' => 100]);
    payment(['amount' => 900], test()->otherShop);

    $stats = $this->get(route('payments.index'))->assertOk()->viewData('statistics');

    expect($stats['payment_count'])->toBe(1)
        ->and((float) $stats['total_amount'])->toBe(100.0);
});

// --- show ---

it('shows a payment from the user\'s shop', function () {
    $payment = payment();

    $this->get(route('payments.show', $payment))
        ->assertOk()
        ->assertSee($payment->payment_number);
});

it('denies viewing a payment belonging to another shop', function () {
    $payment = payment([], test()->otherShop);

    $this->get(route('payments.show', $payment))->assertForbidden();
});

it('binds the payment route by uuid, not id', function () {
    $payment = payment();

    expect($payment->getRouteKeyName())->toBe('uuid')
        ->and($payment->uuid)->not->toBeNull();

    $this->get(route('payments.show', $payment->uuid))->assertOk();
});

it('generates a uuid automatically', function () {
    $sale = Sale::factory()->create(['shop_id' => test()->shop->id]);

    $payment = SalePayment::create([
        'sale_id' => $sale->id,
        'payment_number' => 'PAY-NOUUID1',
        'amount' => 10,
        'payment_method' => 'cash',
        'paid_at' => now(),
    ]);

    expect($payment->uuid)->not->toBeNull();
});

// --- reports ---

it('groups the daily report by day', function () {
    payment(['amount' => 100, 'paid_at' => now()->subDay()]);
    payment(['amount' => 50, 'paid_at' => now()]);
    payment(['amount' => 25, 'paid_at' => now()]);

    $response = $this->get(route('payments.dailyReport'))->assertOk();

    expect($response->viewData('rows'))->toHaveCount(2)
        ->and($response->viewData('totals')['payment_count'])->toBe(3)
        ->and((float) $response->viewData('totals')['total_amount'])->toBe(175.0);
});

it('excludes payments outside the requested window from the daily report', function () {
    payment(['amount' => 500, 'paid_at' => now()->subMonths(6)]);

    $response = $this->get(route('payments.dailyReport', [
        'from' => now()->subDays(7)->toDateString(),
        'to' => now()->toDateString(),
    ]))->assertOk();

    expect($response->viewData('rows'))->toBeEmpty();
});

it('groups the methods report by payment method', function () {
    payment(['amount' => 100, 'payment_method' => 'cash']);
    payment(['amount' => 40, 'payment_method' => 'cash']);
    payment(['amount' => 60, 'payment_method' => 'card']);

    $rows = $this->get(route('payments.methodsReport'))->assertOk()->viewData('rows');

    expect($rows)->toHaveCount(2)
        // Ordered by total desc, so cash (140) comes first.
        ->and($rows->first()->payment_method)->toBe('cash')
        ->and((float) $rows->first()->total_amount)->toBe(140.0);
});

it('keeps another shop\'s payments out of the reports', function () {
    payment(['amount' => 999], test()->otherShop);

    $totals = $this->get(route('payments.methodsReport'))->assertOk()->viewData('totals');

    expect($totals['payment_count'])->toBe(0);
});

// --- authorization ---

it('denies listing without the payments.view permission', function () {
    $outsider = User::factory()->create();
    $outsider->shops()->sync([$this->shop->id]);

    $this->actingAs($outsider)->get(route('payments.index'))->assertForbidden();
});

it('denies the reports without the payments.view permission', function () {
    $outsider = User::factory()->create();
    $outsider->shops()->sync([$this->shop->id]);

    $this->actingAs($outsider)->get(route('payments.dailyReport'))->assertForbidden();
    $this->actingAs($outsider)->get(route('payments.methodsReport'))->assertForbidden();
});

it('requires authentication', function () {
    auth()->logout();

    $this->get(route('payments.index'))->assertRedirect(route('login'));
});
