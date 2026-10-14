<?php

use App\Http\Middleware\ResolveTenant;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\MissingTenantContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Tests\Support\TwoBusinesses;

/**
 * BusinessScope and ShopAccessScope (stage 1a, decisions 4.1 and 4.4), on the
 * two-business fixture.
 */
beforeEach(function () {
    $this->fixture = TwoBusinesses::create();
});

/**
 * Signs in as $user, acting for $business, as ResolveTenant would.
 */
function actFor(User $user, Business $business): void
{
    test()->actingAs($user);
    app(TenantContext::class)->set($business);
}

it('shows an owner every record of their business and none of the other', function () {
    $a = $this->fixture->a;
    actFor($a->owner, $a->business);

    expect(Shop::pluck('id')->all())->toEqualCanonicalizing([$a->shops[0]->id, $a->shops[1]->id])
        ->and(Product::pluck('id')->all())->toBe([$a->product->id])
        ->and(Customer::pluck('id')->all())->toBe([$a->customer->id])
        ->and(Supplier::pluck('id')->all())->toBe([$a->supplier->id])
        ->and(Sale::pluck('id')->all())->toBe([$a->sale->id]);
});

it('limits staff to their assigned shops, but shows business-wide records', function () {
    $a = $this->fixture->a;
    actFor($a->cashier, $a->business);

    // Assigned to the main shop only: the sale in the branch is out of reach.
    expect(Shop::pluck('id')->all())->toBe([$a->shops[0]->id])
        ->and(Sale::count())->toBe(0)
        // Customers and the catalogue belong to the business (Open question 3).
        ->and(Customer::pluck('id')->all())->toBe([$a->customer->id])
        ->and(Product::pluck('id')->all())->toBe([$a->product->id]);
});

it('shows a member of two businesses only the business they act for', function () {
    $both = $this->fixture->both;

    actFor($both, $this->fixture->a->business);
    $inA = [Shop::pluck('id')->all(), Customer::pluck('id')->all()];

    app(TenantContext::class)->set($this->fixture->b->business);
    $inB = [Shop::pluck('id')->all(), Customer::pluck('id')->all()];

    expect($inA)->toBe([[$this->fixture->a->shops[0]->id], [$this->fixture->a->customer->id]])
        ->and($inB)->toBe([[$this->fixture->b->shops[0]->id], [$this->fixture->b->customer->id]]);
});

it('switches the web session between the member\'s businesses', function () {
    $both = $this->fixture->both;
    app(TenantContext::class)->forget();

    $this->actingAs($both)->withSession([ResolveTenant::SESSION_KEY => $this->fixture->b->business->id])
        ->get(route('sales.show', $this->fixture->a->sale))
        ->assertNotFound();
});

it('gives code with no signed-in user every shop of the business it runs for', function () {
    Auth::logout();
    app(TenantContext::class)->set($this->fixture->b->business);

    expect(Shop::count())->toBe(2)
        ->and(Sale::pluck('id')->all())->toBe([$this->fixture->b->sale->id]);
});

it('refuses tenant queries with no business set in strict mode', function () {
    app(TenantContext::class)->forget();

    expect(fn () => Sale::count())->toThrow(MissingTenantContext::class);
});

it('gives a signed-in user with no business no rows outside strict mode', function () {
    config(['tenancy.strict' => false]);
    $this->actingAs($this->fixture->a->owner);
    app(TenantContext::class)->forget();

    expect(Sale::count())->toBe(0)
        ->and(Customer::count())->toBe(0);
});

it('sees across businesses only inside an explicit bypass', function () {
    actFor($this->fixture->a->owner, $this->fixture->a->business);

    $all = app(TenantContext::class)->bypass(fn () => Supplier::pluck('id')->all());

    expect($all)->toEqualCanonicalizing([$this->fixture->a->supplier->id, $this->fixture->b->supplier->id])
        ->and(Supplier::count())->toBe(1);
});

describe('route model binding', function () {
    it('returns 404 for another business\'s record', function () {
        actFor($this->fixture->a->owner, $this->fixture->a->business);

        $this->get(route('sales.show', $this->fixture->b->sale))->assertNotFound();
        $this->get(route('sales.show', $this->fixture->a->sale))->assertOk();
    });

    it('finds the record and acts for its business on a route with no signed-in user', function () {
        Auth::logout();
        app(TenantContext::class)->forget();

        $shop = (new Shop)->resolveRouteBinding($this->fixture->b->shops[0]->uuid, 'uuid');

        expect($shop->id)->toBe($this->fixture->b->shops[0]->id)
            ->and(app(TenantContext::class)->id())->toBe($this->fixture->b->business->id);
    });
});
