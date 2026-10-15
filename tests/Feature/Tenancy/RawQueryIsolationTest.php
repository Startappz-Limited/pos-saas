<?php

use App\Actions\CheckLowStockLevels;
use App\Actions\CreateInventorySnapshot;
use App\Models\EcommerceOrder;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\UserService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TwoBusinesses;

/**
 * The query-builder paths (DB::table, which no model scope reaches) stay
 * within one business (stage 1c acceptance: raw SQL paths and reports tested
 * with two businesses, on MySQL 8 as well as SQLite).
 *
 * Business B holds a completed sale, a sale line and an expense of a marker
 * amount; business A's owner must never see it, and B's owner must, which
 * proves each check can tell.
 */
const MARKER = 987654;

beforeEach(function () {
    $this->fixture = TwoBusinesses::create();
    $b = $this->fixture->b;

    app(TenantContext::class)->run($b->business, function () use ($b): void {
        $sale = Sale::factory()->create([
            'shop_id' => $b->shops[0]->id,
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'total_amount' => MARKER,
            'balance_due' => MARKER,
            'total_cost' => 0,
            'total_profit' => MARKER,
            'tax_amount' => 0,
            'completed_at' => now(),
            'created_at' => now(),
        ]);
        SaleItem::create([
            'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $b->product->id, 'shop_id' => $b->shops[0]->id,
            'quantity' => 1, 'unit_price' => MARKER, 'unit_cost' => 0, 'total_cost' => 0, 'profit' => MARKER,
            'line_total' => MARKER, 'taxable_amount' => MARKER, 'tax_class' => 'standard', 'tax_rate' => 16, 'status' => 'completed',
        ]);
        Expense::factory()->create(['shop_id' => $b->shops[0]->id, 'amount' => MARKER, 'status' => 'approved']);
        EcommerceOrder::factory()->count(3)->create(['shop_id' => $b->shops[0]->id]);
    });

    app(TenantContext::class)->forget();
});

/**
 * Whether the marker amount appears anywhere in $data.
 */
function showsMarker(mixed $data): bool
{
    return str_contains(json_encode($data), (string) MARKER);
}

/**
 * Signs in on the web as $user, in their default business.
 */
function signInWeb(User $user): void
{
    test()->actingAs($user);
    app(TenantContext::class)->forget();
}

it('keeps the web sale statistics and sale reports within the business', function (string $route, string $key) {
    signInWeb($this->fixture->a->owner);
    expect(showsMarker($this->get(route($route))->assertOk()->viewData($key)))->toBeFalse();

    signInWeb($this->fixture->b->owner);
    expect(showsMarker($this->get(route($route))->assertOk()->viewData($key)))->toBeTrue();
})->with([
    // The daily and profit sale reports have no views (a 500 already on
    // main), so their statistics can't be checked through the page.
    'dashboard' => ['dashboard', 'statistics'],
    'reports' => ['reports.index', 'reportData'],
]);

it('counts only the business\'s sales in the sales index statistics', function () {
    foreach ([$this->fixture->a, $this->fixture->b] as $fixture) {
        signInWeb($fixture->owner);
        $total = (int) $this->get(route('sales.index'))->assertOk()->viewData('statistics')['total'];

        expect($total)->toBe(app(TenantContext::class)->run($fixture->business, fn (): int => Sale::count()));
    }
});

it('keeps the VAT summary within the business', function () {
    $query = ['start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->endOfMonth()->toDateString()];

    signInWeb($this->fixture->a->owner);
    expect(showsMarker($this->get(route('reports.vat', $query))->assertOk()->viewData('summary')))->toBeFalse();

    signInWeb($this->fixture->b->owner);
    expect(showsMarker($this->get(route('reports.vat', $query))->assertOk()->viewData('summary')))->toBeTrue();
});

it('keeps the e-commerce order statistics within the business', function () {
    signInWeb($this->fixture->a->owner);
    expect((int) $this->get(route('ecommerce-orders.index'))->assertOk()->viewData('statistics')['total'])->toBe(0);

    signInWeb($this->fixture->b->owner);
    expect((int) $this->get(route('ecommerce-orders.index'))->assertOk()->viewData('statistics')['total'])->toBe(3);
});

it('keeps the flat API reports within the business', function (string $path) {
    Sanctum::actingAs($this->fixture->a->owner, ['*']);
    app(TenantContext::class)->forget();
    expect(showsMarker($this->getJson($path)->assertOk()->json('data')))->toBeFalse();

    Sanctum::actingAs($this->fixture->b->owner, ['*']);
    app(TenantContext::class)->forget();
    expect(showsMarker($this->getJson($path)->assertOk()->json('data')))->toBeTrue();
})->with([
    'daily sales' => '/api/reports/daily-sales',
    'top products' => '/api/reports/top-products',
    'pending payments' => '/api/reports/pending-payments',
]);

it('lists, searches and counts only the business\'s users', function () {
    app(TenantContext::class)->set($this->fixture->a->business);
    $users = app(UserService::class);

    $names = $users->getPaginated(perPage: 100)->pluck('name');
    expect($names)->toContain('Alpha Owner')->not->toContain('Bravo Owner')
        ->and($users->search('Bravo')->pluck('name')->all())->toBe([])
        ->and($users->getStatistics()['total'])->toBe($names->count());
});

it('checks stock and takes snapshots only for the business\'s shops', function () {
    app(TenantContext::class)->set($this->fixture->a->business);
    $bShop = $this->fixture->b->shops[0]->id;

    expect(app(CheckLowStockLevels::class)->execute($bShop))->toBeEmpty()
        ->and(app(CreateInventorySnapshot::class)->execute($bShop))->toBeEmpty();
});
