<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    Permission::create(['name' => 'sales.view']);
    $this->user->givePermissionTo('sales.view');
});

test('index page loads with optimized statistics query', function () {
    Sale::factory()->count(3)->create([
        'shop_id' => $this->shop->id,
        'status' => 'completed',
    ]);
    Sale::factory()->count(2)->create([
        'shop_id' => $this->shop->id,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'paid_amount' => 0,
        'balance_due' => 100,
        'completed_at' => null,
    ]);
    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'status' => 'voided',
    ]);

    $response = $this->get(route('sales.index'));

    $response->assertSuccessful();

    $statistics = $response->viewData('statistics');

    expect($statistics['total'])->toBe(6)
        ->and($statistics['completed'])->toBe(3)
        ->and($statistics['pending'])->toBe(2)
        ->and($statistics['voided'])->toBe(1);
});

test('index page searches by invoice number', function () {
    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'invoice_number' => 'INV-FINDME01',
    ]);
    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'invoice_number' => 'INV-OTHER999',
    ]);

    $response = $this->get(route('sales.index', ['search' => 'FINDME']));

    $response->assertSuccessful();

    $sales = $response->viewData('sales');
    expect($sales)->toHaveCount(1)
        ->and($sales->first()->invoice_number)->toBe('INV-FINDME01');
});

test('index page searches by customer name without N+1', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::create([
        'uuid' => Str::uuid(),
        'shop_id' => $shop->id,
        'code' => 'CUST-001',
        'name' => 'John Doe',
        'customer_type' => 'retail',
        'status' => 'active',
    ]);

    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'customer_id' => $customer->id,
    ]);
    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'customer_id' => null,
    ]);

    $response = $this->get(route('sales.index', ['search' => 'John']));

    $response->assertSuccessful();

    $sales = $response->viewData('sales');
    expect($sales)->toHaveCount(1)
        ->and($sales->first()->customer_id)->toBe($customer->id);
});

test('index page filters by status', function () {
    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'status' => 'completed',
    ]);
    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'paid_amount' => 0,
        'balance_due' => 100,
        'completed_at' => null,
    ]);

    $response = $this->get(route('sales.index', ['status' => 'pending']));

    $response->assertSuccessful();

    $sales = $response->viewData('sales');
    expect($sales)->toHaveCount(1)
        ->and($sales->first()->status)->toBe('pending');
});

test('index page filters by payment status', function () {
    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'payment_status' => 'paid',
    ]);
    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'payment_status' => 'unpaid',
        'status' => 'pending',
        'paid_amount' => 0,
        'balance_due' => 100,
        'completed_at' => null,
    ]);

    $response = $this->get(route('sales.index', ['payment_status' => 'unpaid']));

    $response->assertSuccessful();

    $sales = $response->viewData('sales');
    expect($sales)->toHaveCount(1)
        ->and($sales->first()->payment_status)->toBe('unpaid');
});

test('index page only includes assigned shop sales for allocated users', function () {
    $this->user->shops()->sync([$this->shop->id]);
    $otherShop = Shop::factory()->create();

    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'status' => 'completed',
    ]);
    Sale::factory()->create([
        'shop_id' => $otherShop->id,
        'status' => 'completed',
    ]);

    $response = $this->get(route('sales.index'));

    $response->assertSuccessful();

    $sales = $response->viewData('sales');
    $statistics = $response->viewData('statistics');

    expect($sales)->toHaveCount(1)
        ->and($sales->first()->shop_id)->toBe($this->shop->id)
        ->and((int) $statistics['total'])->toBe(1);
});

test('allocated user cannot view sales from another shop', function () {
    $this->user->shops()->sync([$this->shop->id]);
    $otherShop = Shop::factory()->create();
    $sale = Sale::factory()->create(['shop_id' => $otherShop->id]);

    $this->get(route('sales.show', $sale))->assertForbidden();
});

test('allocated user cannot complete sales from another shop', function () {
    Permission::create(['name' => 'sales.update']);
    $this->user->givePermissionTo('sales.update');
    $this->user->shops()->sync([$this->shop->id]);
    $otherShop = Shop::factory()->create();
    $sale = Sale::factory()->create(['shop_id' => $otherShop->id]);

    $this->post(route('sales.complete', $sale))->assertForbidden();
});

test('credit sale requires a wholesale customer', function () {
    Permission::create(['name' => 'sales.create']);
    $this->user->givePermissionTo('sales.create');

    $source = SaleSource::create([
        'name' => 'Counter',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $product = Product::factory()->create([
        'stock_quantity' => 10,
        'selling_price' => 100,
        'cost_price' => 50,
    ]);
    $customer = Customer::factory()->create([
        'shop_id' => $this->shop->id,
        'customer_type' => 'retail',
    ]);

    $this->post(route('sales.store'), [
        'customer_id' => $customer->id,
        'source_id' => $source->id,
        'delivery_location' => 'Front counter',
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => 100,
            ],
        ],
        'payment_method' => 'credit',
    ])->assertSessionHasErrors('customer_id');

    expect(Sale::count())->toBe(0);
});
