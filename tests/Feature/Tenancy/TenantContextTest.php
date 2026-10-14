<?php

use App\Http\Middleware\ResolveTenant;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\MissingTenantContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/**
 * Which business a request, job or record belongs to (stage 1a, decisions
 * 4.3 and 4.4).
 */
beforeEach(function () {
    // Report the business each request resolved to.
    Route::middleware(['web', 'auth'])->get('/_test/tenant', fn () => response()->json(['business' => app(TenantContext::class)->id()]));
    Route::middleware(['api', 'auth:sanctum'])->get('/api/_test/tenant', fn () => response()->json(['business' => app(TenantContext::class)->id()]));
});

describe('records', function () {
    it('gives a new business its default sale source', function () {
        $business = Business::factory()->create();

        expect(SaleSource::where('business_id', $business->id)->pluck('name')->all())->toBe(['Walk-in']);
    });

    it('places a record in its parent\'s business, not the current one', function () {
        $other = Business::factory()->create();
        $shop = Shop::factory()->create(['business_id' => $other->id]);
        $product = Product::factory()->create(['business_id' => $other->id]);

        expect(Customer::factory()->forShop($shop)->create()->business_id)->toBe($other->id)
            ->and(ProductVariation::factory()->create(['product_id' => $product->id])->business_id)->toBe($other->id);
    });

    it('places a record without a parent in the current business', function () {
        expect(Supplier::factory()->create()->business_id)->toBe(testBusiness()->id);
    });

    it('refuses a record that belongs to no business', function () {
        app(TenantContext::class)->forget();

        expect(fn () => Supplier::factory()->create())->toThrow(MissingTenantContext::class);
    });

    it('restores the previous business after running code for another', function () {
        $other = Business::factory()->create();

        $inside = app(TenantContext::class)->run($other, fn () => app(TenantContext::class)->id());

        expect($inside)->toBe($other->id)
            ->and(app(TenantContext::class)->id())->toBe(testBusiness()->id);
    });
});

describe('memberships', function () {
    it('makes the first business a user joins their default', function () {
        $user = User::factory()->create();
        $second = Business::factory()->create();
        $user->joinBusiness($second);

        expect($user->fresh()->default_business_id)->toBe(testBusiness()->id)
            ->and($user->businesses()->pluck('businesses.id')->all())->toEqualCanonicalizing([testBusiness()->id, $second->id]);
    });

    it('opens a business only through an active membership of an active business', function () {
        $user = User::factory()->create();
        $suspended = Business::factory()->suspended()->create();
        $user->joinBusiness($suspended);
        $left = Business::factory()->create();
        $user->joinBusiness($left)->update(['status' => BusinessMembership::STATUS_INACTIVE]);

        expect($user->canEnterBusiness(testBusiness()))->toBeTrue()
            ->and($user->canEnterBusiness($suspended))->toBeFalse()
            ->and($user->canEnterBusiness($left))->toBeFalse()
            ->and($user->canEnterBusiness(Business::factory()->create()))->toBeFalse();
    });
});

describe('requests', function () {
    beforeEach(fn () => app(TenantContext::class)->forget());

    it('acts for the user\'s default business on the web', function () {
        $user = User::factory()->memberOf(Business::factory()->create())->create();

        $this->actingAs($user)->getJson('/_test/tenant')->assertJson(['business' => $user->default_business_id]);
    });

    it('acts for the business chosen for the session while the user is still a member', function () {
        $user = User::factory()->create();
        $second = Business::factory()->create();
        $user->joinBusiness($second);

        $this->actingAs($user)->withSession([ResolveTenant::SESSION_KEY => $second->id])
            ->getJson('/_test/tenant')->assertJson(['business' => $second->id]);
    });

    it('falls back to the default business when the session names one the user has left', function () {
        $user = User::factory()->memberOf(Business::factory()->create())->create();
        $left = Business::factory()->create();
        $user->joinBusiness($left)->update(['status' => BusinessMembership::STATUS_INACTIVE]);

        $this->actingAs($user)->withSession([ResolveTenant::SESSION_KEY => $left->id])
            ->getJson('/_test/tenant')->assertJson(['business' => $user->default_business_id])
            ->assertSessionMissing(ResolveTenant::SESSION_KEY);
    });

    it('acts for the default business with an API token, as legacy flat API tokens do', function () {
        $user = User::factory()->memberOf(Business::factory()->create())->create();
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/_test/tenant')->assertJson(['business' => $user->default_business_id]);
    });

    it('opens no business for a user without a usable one', function () {
        $suspended = Business::factory()->suspended()->create();
        $user = User::factory()->memberOf($suspended)->create();

        $this->actingAs($user)->getJson('/_test/tenant')->assertJson(['business' => null]);
    });
});
