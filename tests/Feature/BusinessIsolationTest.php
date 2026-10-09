<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * An admin (shop owner) sees only their own business: its shops and everything
 * in them, plus the lists the business shares (suppliers, categories...).
 * Staff see only the shops they are linked to, and staff linked to no shop are
 * sent to the "contact your administrator" page. A super-admin sees it all.
 *
 * Two businesses, Alpha and Bravo, with distinctive names so a leak shows up
 * in both the web pages and the API.
 */
beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->alpha = Business::factory()->create(['name' => 'Alpha Ltd']);
    $this->bravo = Business::factory()->create(['name' => 'Bravo Ltd']);

    $this->alphaAdmin = User::factory()->create(['business_id' => $this->alpha->id]);
    $this->alphaAdmin->assignRole(Role::ADMIN);
    $this->bravoAdmin = User::factory()->create(['business_id' => $this->bravo->id]);
    $this->bravoAdmin->assignRole(Role::ADMIN);

    $this->alphaShop = Shop::factory()->create(['business_id' => $this->alpha->id, 'name' => 'Alpha Main Shop', 'manager_id' => $this->alphaAdmin->id]);
    $this->alphaBranch = Shop::factory()->create(['business_id' => $this->alpha->id, 'name' => 'Alpha Branch Shop', 'manager_id' => $this->alphaAdmin->id]);
    $this->bravoShop = Shop::factory()->create(['business_id' => $this->bravo->id, 'name' => 'Bravo Main Shop', 'manager_id' => $this->bravoAdmin->id]);

    $this->alphaSupplier = Supplier::factory()->create(['business_id' => $this->alpha->id, 'name' => 'Alpha Supplies']);
    $this->bravoSupplier = Supplier::factory()->create(['business_id' => $this->bravo->id, 'name' => 'Bravo Supplies']);
    $this->alphaCategory = Category::factory()->create(['business_id' => $this->alpha->id, 'name' => 'Alpha Drinks']);
    $this->bravoCategory = Category::factory()->create(['business_id' => $this->bravo->id, 'name' => 'Bravo Drinks']);

    $this->alphaProduct = Product::factory()->create(['shop_id' => $this->alphaShop->id, 'name' => 'Alpha Main Widget']);
    $this->alphaBranchProduct = Product::factory()->create(['shop_id' => $this->alphaBranch->id, 'name' => 'Alpha Branch Widget']);
    $this->bravoProduct = Product::factory()->create(['shop_id' => $this->bravoShop->id, 'name' => 'Bravo Widget']);

    $this->alphaCustomer = Customer::factory()->forShop($this->alphaShop)->create(['name' => 'Alpha Customer']);
    $this->bravoCustomer = Customer::factory()->forShop($this->bravoShop)->create(['name' => 'Bravo Customer']);
});

/**
 * A staff member of the Alpha business linked to the given shops.
 *
 * @param  array<int, Shop>  $shops
 */
function alphaStaff(array $shops, array $permissions = []): User
{
    $user = User::factory()->create(['business_id' => test()->alpha->id]);
    $user->assignRole('cashier');
    $user->givePermissionTo($permissions);
    $user->shops()->attach(collect($shops)->pluck('id'));

    return $user;
}

describe('an admin', function () {
    it('sees only their own shops', function () {
        $this->actingAs($this->alphaAdmin)->get(route('shops.index'))
            ->assertOk()
            ->assertSee('Alpha Main Shop')
            ->assertSee('Alpha Branch Shop')
            ->assertDontSee('Bravo Main Shop');
    });

    it('sees only their own products, suppliers, categories and customers on the web', function (string $route, string $own, string $foreign) {
        $this->actingAs($this->alphaAdmin)->get(route($route))
            ->assertOk()
            ->assertSee($own)
            ->assertDontSee($foreign);
    })->with([
        'products' => ['products.index', 'Alpha Main Widget', 'Bravo Widget'],
        'suppliers' => ['suppliers.index', 'Alpha Supplies', 'Bravo Supplies'],
        'categories' => ['categories.index', 'Alpha Drinks', 'Bravo Drinks'],
        'customers' => ['customers.index', 'Alpha Customer', 'Bravo Customer'],
    ]);

    it('sees only their own data over the API', function (string $uri, string $own, string $foreign) {
        Sanctum::actingAs($this->alphaAdmin);

        $this->getJson($uri)
            ->assertOk()
            ->assertSee($own)
            ->assertDontSee($foreign);
    })->with([
        'shops' => ['/api/shops', 'Alpha Main Shop', 'Bravo Main Shop'],
        'products' => ['/api/products', 'Alpha Main Widget', 'Bravo Widget'],
        'suppliers' => ['/api/suppliers', 'Alpha Supplies', 'Bravo Supplies'],
        'categories' => ['/api/categories', 'Alpha Drinks', 'Bravo Drinks'],
        'customers' => ['/api/customers', 'Alpha Customer', 'Bravo Customer'],
    ]);

    it('gets a 404 for another business\'s records', function () {
        $this->actingAs($this->alphaAdmin);

        $this->get(route('shops.show', $this->bravoShop))->assertNotFound();
        $this->get(route('products.show', $this->bravoProduct))->assertNotFound();
        $this->get(route('suppliers.show', $this->bravoSupplier))->assertNotFound();
        $this->getJson("/api/products/{$this->bravoProduct->uuid}")->assertNotFound();
    });

    it('cannot put records into another business\'s shop or reference its lists', function () {
        $this->actingAs($this->alphaAdmin)
            ->post(route('products.store'), [
                'name' => 'Smuggled',
                'shop_id' => $this->bravoShop->id,
                'category_id' => $this->bravoCategory->id,
                'supplier_id' => $this->bravoSupplier->id,
                'selling_price' => 100,
                'stock_quantity' => 1,
            ])
            ->assertSessionHasErrors(['shop_id', 'category_id', 'supplier_id']);

        expect(Product::withoutGlobalScopes()->where('name', 'Smuggled')->exists())->toBeFalse();
    });

    it('stamps the shared lists they create with their business', function () {
        $this->actingAs($this->alphaAdmin);

        $supplier = Supplier::create(['name' => 'Fresh Supplier', 'status' => 'active']);

        expect($supplier->business_id)->toBe($this->alpha->id);
    });

    it('may reuse a name another business already uses', function () {
        $this->actingAs($this->bravoAdmin);
        SaleSource::create(['name' => 'Market Stall']);

        $this->actingAs($this->alphaAdmin);
        SaleSource::create(['name' => 'Market Stall']);

        expect(SaleSource::withoutGlobalScopes()->where('name', 'Market Stall')->count())->toBe(2);
    });

    it('sees and manages only the users of their own business', function () {
        $alphaStaff = alphaStaff([$this->alphaShop], ['users.view']);

        $this->actingAs($this->alphaAdmin);

        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee($alphaStaff->email)
            ->assertDontSee($this->bravoAdmin->email);

        $this->get(route('users.show', $this->bravoAdmin))->assertForbidden();
    });

    it('cannot link staff to another business\'s shop', function () {
        $this->actingAs($this->alphaAdmin)
            ->postJson('/api/users', [
                'name' => 'New Cashier',
                'email' => 'new-cashier@example.test',
                'password' => 'Secret-Pass-123!',
                'roles' => ['cashier'],
                'shop_id' => $this->bravoShop->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shop_id');
    });

    it('puts the staff they create in their business and shop', function () {
        Sanctum::actingAs($this->alphaAdmin);

        $this->postJson('/api/users', [
            'name' => 'New Cashier',
            'email' => 'new-cashier@example.test',
            'password' => 'Secret-Pass-123!',
            'roles' => ['cashier'],
            'shop_id' => $this->alphaShop->id,
        ])->assertCreated();

        $cashier = User::where('email', 'new-cashier@example.test')->sole();

        expect($cashier->business_id)->toBe($this->alpha->id)
            ->and($cashier->assignedShopIds()->all())->toBe([$this->alphaShop->id]);
    });

    it('sees only their own business in reports', function () {
        $this->actingAs($this->alphaAdmin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('shops', fn ($shops) => $shops->pluck('id')->sort()->values()->all()
                === collect([$this->alphaShop->id, $this->alphaBranch->id])->sort()->values()->all());

        $this->get(route('reports.index', ['shop_id' => $this->bravoShop->id]))->assertForbidden();
    });
});

describe('an admin with no shop yet', function () {
    it('gets a business and an empty dashboard rather than the no-shop page', function () {
        $newAdmin = User::factory()->create(['business_id' => null]);
        $newAdmin->assignRole(Role::ADMIN);

        $this->actingAs($newAdmin)->get(route('dashboard'))->assertOk();

        $newAdmin->refresh();

        expect($newAdmin->business_id)->not->toBeNull()
            ->and($newAdmin->business_id)->not->toBe($this->alpha->id)
            ->and($newAdmin->accessibleShopIds())->toBeEmpty();

        $this->get(route('products.index'))
            ->assertOk()
            ->assertDontSee('Alpha Main Widget')
            ->assertDontSee('Bravo Widget');
    });

    it('starts their business with the default sale sources', function () {
        $business = Business::factory()->create();

        expect(SaleSource::withoutGlobalScopes()->where('business_id', $business->id)->pluck('name')->all())
            ->toContain('Website', 'WhatsApp', 'Abandoned Cart');
    });
});

describe('staff', function () {
    it('see only the shops they are linked to', function () {
        $cashier = alphaStaff([$this->alphaShop], ['products.view', 'customers.view']);

        $this->actingAs($cashier)->get(route('products.index'))
            ->assertOk()
            ->assertSee('Alpha Main Widget')
            ->assertDontSee('Alpha Branch Widget')
            ->assertDontSee('Bravo Widget');

        $this->get(route('products.show', $this->alphaBranchProduct))->assertNotFound();
    });

    it('share their business\'s suppliers and categories', function () {
        $cashier = alphaStaff([$this->alphaShop], ['suppliers.view']);

        $this->actingAs($cashier)->get(route('suppliers.index'))
            ->assertOk()
            ->assertSee('Alpha Supplies')
            ->assertDontSee('Bravo Supplies');
    });

    it('get the contact-your-administrator page when linked to no shop', function () {
        $unlinked = User::factory()->create(['business_id' => $this->alpha->id]);
        $unlinked->assignRole('cashier');

        $this->actingAs($unlinked);

        $this->get(route('dashboard'))->assertRedirect(route('no-shop'));
        $this->get(route('products.index'))->assertRedirect(route('no-shop'));

        $this->get(route('no-shop'))
            ->assertOk()
            ->assertSee('You are not linked to a shop yet')
            ->assertSee('contact your administrator');
    });

    it('can still sign out and edit their profile when linked to no shop', function () {
        $unlinked = User::factory()->create(['business_id' => $this->alpha->id]);

        $this->actingAs($unlinked)->get(route('profile.edit'))->assertOk();
    });

    it('get a 403 with a code from the API when linked to no shop', function () {
        $unlinked = User::factory()->create(['business_id' => $this->alpha->id]);
        $unlinked->assignRole('cashier');
        Sanctum::actingAs($unlinked);

        $this->getJson('/api/products')
            ->assertForbidden()
            ->assertJson(['success' => false, 'code' => 'shop_not_linked']);

        // The app can still find out who is signed in, and sign out
        $this->getJson('/api/user')->assertOk();
    });

    it('are sent on from the no-shop page once linked', function () {
        $cashier = alphaStaff([$this->alphaShop]);

        $this->actingAs($cashier)->get(route('no-shop'))->assertRedirect(route('dashboard'));
    });
});

describe('a super-admin', function () {
    it('sees every business', function () {
        $superAdmin = User::factory()->create(['business_id' => null]);
        $superAdmin->assignRole(Role::SUPER_ADMIN);

        $this->actingAs($superAdmin)->get(route('products.index'))
            ->assertOk()
            ->assertSee('Alpha Main Widget')
            ->assertSee('Bravo Widget');

        $this->get(route('shops.show', $this->bravoShop))->assertOk();
    });
});
