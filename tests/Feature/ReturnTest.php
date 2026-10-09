<?php

use App\Enums\ReturnReason;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function grantReturnPermissions(User $user, array $permissions): void
{
    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission]);
    }

    $user->givePermissionTo($permissions);
}

function actingAsReturnUser(array $permissions): User
{
    /** @var User $user */
    $user = User::factory()->create();

    grantReturnPermissions($user, $permissions);
    actingAs($user);

    return $user;
}

test('authorized user can view return create page with completed sale items', function () {
    actingAsReturnUser(['returns.create']);

    $sale = Sale::factory()->create([
        'invoice_number' => 'INV-RET-001',
        'status' => 'completed',
    ]);
    $product = Product::factory()->create(['name' => 'Returnable Product']);
    $saleItem = SaleItem::create([
        'uuid' => (string) Str::uuid(),
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 25,
        'line_total' => 50,
        'status' => 'completed',
    ]);

    get(route('returns.create', ['sale_id' => $sale->id]))
        ->assertOk()
        ->assertSee($sale->invoice_number)
        ->assertSee($product->name)
        ->assertSee('name="items[0][sale_item_id]"', false)
        ->assertSee('value="'.$saleItem->id.'"', false);
});

test('return create sale dropdown includes customer phone number', function () {
    actingAsReturnUser(['returns.create']);

    $customer = Customer::factory()->create([
        'name' => 'Alice Customer',
        'phone' => '555-0199',
    ]);
    $sale = Sale::factory()->create([
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-RET-CUSTOMER',
        'status' => 'completed',
    ]);

    get(route('returns.create'))
        ->assertOk()
        ->assertSee($sale->invoice_number)
        ->assertSee($customer->name)
        ->assertSee($customer->phone);
});

test('authorized user can filter return sale list by customer phone', function () {
    actingAsReturnUser(['returns.create']);

    $matchingCustomer = Customer::factory()->create([
        'name' => 'Alice Customer',
        'phone' => '555-0199',
    ]);
    $matchingSale = Sale::factory()->create([
        'customer_id' => $matchingCustomer->id,
        'invoice_number' => 'INV-RET-SEARCH',
        'status' => 'completed',
    ]);
    Sale::factory()->create([
        'walk_in_customer_name' => 'Someone Else',
        'walk_in_customer_phone' => '555-0100',
        'invoice_number' => 'INV-RET-OTHER',
        'status' => 'completed',
    ]);

    get(route('returns.create', ['sale_search' => $matchingCustomer->phone]))
        ->assertOk()
        ->assertSee('name="sale_search"', false)
        ->assertSee($matchingSale->invoice_number)
        ->assertSee($matchingCustomer->name)
        ->assertSee($matchingCustomer->phone)
        ->assertDontSee('INV-RET-OTHER');
});

test('authorized user can submit a return for a walk in sale', function () {
    actingAsReturnUser(['returns.create']);

    $sale = Sale::factory()->create([
        'customer_id' => null,
        'invoice_number' => 'INV-RET-WALKIN',
        'status' => 'completed',
    ]);
    $product = Product::factory()->create();
    $saleItem = SaleItem::create([
        'uuid' => (string) Str::uuid(),
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 25,
        'line_total' => 50,
        'status' => 'completed',
    ]);

    post(route('returns.store'), [
        'sale_id' => $sale->id,
        'reason' => ReturnReason::WRONG_ITEM->value,
        'restocking_fee' => 0,
        'items' => [
            [
                'sale_item_id' => $saleItem->id,
                'quantity' => 1,
                'condition' => 'new',
            ],
        ],
    ])->assertRedirect();

    assertDatabaseHas('returns', [
        'sale_id' => $sale->id,
        'customer_id' => null,
        'shop_id' => $sale->shop_id,
    ]);
    assertDatabaseHas('return_items', [
        'sale_item_id' => $saleItem->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
});

test('authorized user can view customer name on returns index', function () {
    actingAsReturnUser(['returns.view']);

    $customer = Customer::factory()->create([
        'name' => 'Alice Customer',
    ]);
    $sale = Sale::factory()->create([
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-RET-INDEX',
        'status' => 'completed',
    ]);
    $saleReturn = SaleReturn::factory()->create([
        'sale_id' => $sale->id,
        'customer_id' => null,
        'shop_id' => $sale->shop_id,
    ]);

    get(route('returns.index'))
        ->assertOk()
        ->assertSee($saleReturn->return_number)
        ->assertSee($customer->name);
});

test('authorized user can view returns for a sale', function () {
    actingAsReturnUser(['returns.view']);

    $sale = Sale::factory()->create([
        'invoice_number' => 'INV-RET-002',
        'status' => 'completed',
    ]);

    get(route('returns.forSale', $sale))
        ->assertOk()
        ->assertSee($sale->invoice_number);
});

test('full access user only sees available return workflow actions', function () {
    actingAsReturnUser(['returns.full-access']);

    $approvedReturn = SaleReturn::factory()->approved()->create();
    get(route('returns.show', $approvedReturn))
        ->assertOk()
        ->assertDontSee(route('returns.approve', $approvedReturn), false)
        ->assertSee(route('returns.receive', $approvedReturn), false)
        ->assertDontSee('data-bs-target="#inspectModal"', false);

    $receivedReturn = SaleReturn::factory()->received()->create();
    get(route('returns.show', $receivedReturn))
        ->assertOk()
        ->assertDontSee(route('returns.approve', $receivedReturn), false)
        ->assertDontSee(route('returns.receive', $receivedReturn), false)
        ->assertSee('data-bs-target="#inspectModal"', false);

    $inspectedReturn = SaleReturn::factory()->inspected()->create();
    get(route('returns.show', $inspectedReturn))
        ->assertOk()
        ->assertDontSee(route('returns.approve', $inspectedReturn), false)
        ->assertDontSee(route('returns.receive', $inspectedReturn), false)
        ->assertDontSee(route('returns.inspect', $inspectedReturn), false)
        ->assertDontSee('data-bs-target="#inspectModal"', false);
});
