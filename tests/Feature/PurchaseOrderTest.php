<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// Authorization Tests
test('authorized user can view purchase orders', function () {
    Permission::create(['name' => 'purchase_orders.view']);
    $this->user->givePermissionTo('purchase_orders.view');

    PurchaseOrder::factory()->count(3)->create();

    $response = $this->get(route('purchase-orders.index'));

    $response->assertOk();
});

test('unauthorized user cannot view purchase orders', function () {
    $response = $this->get(route('purchase-orders.index'));

    $response->assertForbidden();
});

test('authorized user can create purchase order', function () {
    Permission::create(['name' => 'purchase_orders.create']);
    $this->user->givePermissionTo('purchase_orders.create');

    $supplier = Supplier::factory()->create();
    $shop = Shop::factory()->create();
    $product = Product::factory()->create();
    $product->shops()->attach($shop);

    $data = [
        'supplier_id' => $supplier->id,
        'shop_id' => $shop->id,
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => now()->addDays(7)->toDateString(),
        'items' => [
            [
                'product_id' => $product->id,
                'quantity_ordered' => 100,
                'unit_cost' => 50,
            ],
        ],
    ];

    $response = $this->post(route('purchase-orders.store'), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('purchase_orders', [
        'supplier_id' => $supplier->id,
        'shop_id' => $shop->id,
    ]);
});

test('authorized user can submit purchase order for approval when creating', function () {
    Permission::create(['name' => 'purchase_orders.create']);
    $this->user->givePermissionTo('purchase_orders.create');

    $supplier = Supplier::factory()->create();
    $shop = Shop::factory()->create();
    $product = Product::factory()->create();
    $product->shops()->attach($shop);

    $response = $this->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'shop_id' => $shop->id,
        'status' => PurchaseOrderStatus::PENDING->value,
        'order_date' => now()->toDateString(),
        'items' => [
            [
                'product_id' => $product->id,
                'quantity_ordered' => 20,
                'unit_cost' => 1000,
            ],
        ],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('purchase_orders', [
        'supplier_id' => $supplier->id,
        'shop_id' => $shop->id,
        'status' => PurchaseOrderStatus::PENDING->value,
    ]);
});

test('authorized user can create approve and mark purchase order as ordered', function () {
    Permission::create(['name' => 'purchase_orders.create']);
    Permission::create(['name' => 'purchase_orders.approve']);
    Permission::create(['name' => 'purchase_orders.update']);
    $this->user->givePermissionTo([
        'purchase_orders.create',
        'purchase_orders.approve',
        'purchase_orders.update',
    ]);

    $supplier = Supplier::factory()->create();
    $shop = Shop::factory()->create();
    $product = Product::factory()->create();
    $product->shops()->attach($shop);

    $response = $this->post(route('purchase-orders.store'), [
        'supplier_id' => $supplier->id,
        'shop_id' => $shop->id,
        'workflow' => 'approve_and_mark_ordered',
        'order_date' => now()->toDateString(),
        'items' => [
            [
                'product_id' => $product->id,
                'quantity_ordered' => 20,
                'unit_cost' => 1000,
            ],
        ],
    ]);

    $response->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()->where('supplier_id', $supplier->id)->firstOrFail();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::ORDERED)
        ->and($purchaseOrder->approved_by)->toBe($this->user->id)
        ->and($purchaseOrder->approved_at)->not->toBeNull();
});

// Validation Tests
test('purchase order supplier is required', function () {
    Permission::create(['name' => 'purchase_orders.create']);
    $this->user->givePermissionTo('purchase_orders.create');

    $data = [
        'shop_id' => Shop::factory()->create()->id,
        'order_date' => now()->toDateString(),
        'items' => [],
    ];

    $response = $this->post(route('purchase-orders.store'), $data);

    $response->assertSessionHasErrors('supplier_id');
});

test('purchase order requires at least one item', function () {
    Permission::create(['name' => 'purchase_orders.create']);
    $this->user->givePermissionTo('purchase_orders.create');

    $data = [
        'supplier_id' => Supplier::factory()->create()->id,
        'shop_id' => Shop::factory()->create()->id,
        'order_date' => now()->toDateString(),
        'items' => [],
    ];

    $response = $this->post(route('purchase-orders.store'), $data);

    $response->assertSessionHasErrors('items');
});

test('expected delivery date must be after order date', function () {
    Permission::create(['name' => 'purchase_orders.create']);
    $this->user->givePermissionTo('purchase_orders.create');

    $shop = Shop::factory()->create();
    $product = Product::factory()->create();
    $product->shops()->attach($shop);

    $data = [
        'supplier_id' => Supplier::factory()->create()->id,
        'shop_id' => $shop->id,
        'order_date' => now()->toDateString(),
        'expected_delivery_date' => now()->subDay()->toDateString(),
        'items' => [
            [
                'product_id' => $product->id,
                'quantity_ordered' => 10,
                'unit_cost' => 20,
            ],
        ],
    ];

    $response = $this->post(route('purchase-orders.store'), $data);

    $response->assertSessionHasErrors('expected_delivery_date');
});

test('purchase order product must be assigned to selected shop', function () {
    Permission::create(['name' => 'purchase_orders.create']);
    $this->user->givePermissionTo('purchase_orders.create');

    $selectedShop = Shop::factory()->create();
    $otherShop = Shop::factory()->create();
    $product = Product::factory()->create();
    $product->shops()->attach($otherShop);

    $response = $this->post(route('purchase-orders.store'), [
        'supplier_id' => Supplier::factory()->create()->id,
        'shop_id' => $selectedShop->id,
        'order_date' => now()->toDateString(),
        'items' => [
            [
                'product_id' => $product->id,
                'quantity_ordered' => 10,
                'unit_cost' => 20,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.product_id');
});

// CRUD Tests
test('can update purchase order', function () {
    Permission::create(['name' => 'purchase_orders.update']);
    $this->user->givePermissionTo('purchase_orders.update');

    $purchaseOrder = PurchaseOrder::factory()->draft()->create();

    $data = [
        'supplier_id' => $purchaseOrder->supplier_id,
        'shop_id' => $purchaseOrder->shop_id,
        'order_date' => $purchaseOrder->order_date->toDateString(),
        'notes' => 'Updated notes',
    ];

    $response = $this->put(route('purchase-orders.update', $purchaseOrder), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('purchase_orders', [
        'id' => $purchaseOrder->id,
        'notes' => 'Updated notes',
    ]);
});

test('can delete draft purchase order', function () {
    Permission::create(['name' => 'purchase_orders.delete']);
    $this->user->givePermissionTo('purchase_orders.delete');

    $purchaseOrder = PurchaseOrder::factory()->draft()->create();

    $response = $this->delete(route('purchase-orders.destroy', $purchaseOrder));

    $response->assertRedirect();
    $this->assertSoftDeleted('purchase_orders', ['id' => $purchaseOrder->id]);
});

test('can approve pending purchase order', function () {
    Permission::create(['name' => 'purchase_orders.approve']);
    $this->user->givePermissionTo('purchase_orders.approve');

    $purchaseOrder = PurchaseOrder::factory()->pending()->create();

    $response = $this->post(route('purchase-orders.approve', $purchaseOrder));

    $response->assertRedirect();
    $purchaseOrder->refresh();
    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::APPROVED);
});

test('can approve and mark pending purchase order as ordered', function () {
    Permission::create(['name' => 'purchase_orders.approve']);
    Permission::create(['name' => 'purchase_orders.update']);
    $this->user->givePermissionTo(['purchase_orders.approve', 'purchase_orders.update']);

    $purchaseOrder = PurchaseOrder::factory()->pending()->create();

    $response = $this->post(route('purchase-orders.approveAndMarkAsOrdered', $purchaseOrder));

    $response->assertRedirect();
    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::ORDERED)
        ->and($purchaseOrder->approved_by)->toBe($this->user->id)
        ->and($purchaseOrder->approved_at)->not->toBeNull();
});

test('can submit draft purchase order for approval', function () {
    Permission::create(['name' => 'purchase_orders.update']);
    $this->user->givePermissionTo('purchase_orders.update');

    $purchaseOrder = PurchaseOrder::factory()->draft()->create();

    $response = $this->post(route('purchase-orders.submitForApproval', $purchaseOrder));

    $response->assertRedirect();
    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::PENDING);
});

test('can mark approved purchase order as ordered', function () {
    Permission::create(['name' => 'purchase_orders.update']);
    $this->user->givePermissionTo('purchase_orders.update');

    $purchaseOrder = PurchaseOrder::factory()->approved()->create();

    $response = $this->post(route('purchase-orders.markAsOrdered', $purchaseOrder));

    $response->assertRedirect();
    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::ORDERED);
});

test('can submit approve and mark draft purchase order as ordered', function () {
    Permission::create(['name' => 'purchase_orders.approve']);
    Permission::create(['name' => 'purchase_orders.update']);
    $this->user->givePermissionTo(['purchase_orders.approve', 'purchase_orders.update']);

    $purchaseOrder = PurchaseOrder::factory()->draft()->create();

    $response = $this->post(route('purchase-orders.submitApproveAndMarkAsOrdered', $purchaseOrder));

    $response->assertRedirect();
    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::ORDERED)
        ->and($purchaseOrder->approved_by)->toBe($this->user->id)
        ->and($purchaseOrder->approved_at)->not->toBeNull();
});

test('user with purchase order full access can approve own pending purchase order', function () {
    Permission::create(['name' => 'purchase_orders.full-access']);
    $this->user->givePermissionTo('purchase_orders.full-access');

    $purchaseOrder = PurchaseOrder::factory()->pending()->create([
        'created_by' => $this->user->id,
    ]);

    $response = $this->post(route('purchase-orders.approve', $purchaseOrder));

    $response->assertRedirect();
    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::APPROVED)
        ->and($purchaseOrder->approved_by)->toBe($this->user->id);
});

test('seeded manager role can approve purchase orders', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $this->user->assignRole('manager');

    expect($this->user->can('purchase_orders.approve'))->toBeTrue();
});

test('can cancel purchase order', function () {
    Permission::create(['name' => 'purchase_orders.update']);
    $this->user->givePermissionTo('purchase_orders.update');

    $purchaseOrder = PurchaseOrder::factory()->pending()->create();

    $response = $this->post(route('purchase-orders.cancel', $purchaseOrder));

    $response->assertRedirect();
    $purchaseOrder->refresh();
    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::CANCELLED);
});

// Model Tests
test('purchase order factory creates valid purchase order', function () {
    $purchaseOrder = PurchaseOrder::factory()->create();

    expect($purchaseOrder)->toBeInstanceOf(PurchaseOrder::class)
        ->and($purchaseOrder->uuid)->not->toBeNull()
        ->and($purchaseOrder->order_number)->toStartWith('PO-');
});

test('purchase order has items relationship', function () {
    // The relationship is items(), not purchaseOrderItems(), so has() cannot infer
    // it from the related model name — pass it explicitly.
    $purchaseOrder = PurchaseOrder::factory()
        ->has(PurchaseOrderItem::factory()->count(3), 'items')
        ->create();

    expect($purchaseOrder->items)->toHaveCount(3);
});

test('purchase order calculates totals correctly', function () {
    $purchaseOrder = PurchaseOrder::factory()->create([
        'subtotal' => 0,
        'tax_amount' => 0,
        'total_amount' => 0,
    ]);

    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity_ordered' => 10,
        'unit_cost' => 100,
        'tax_rate' => 0,
        'discount_percent' => 0,
    ]);

    $purchaseOrder->calculateTotals();

    // decimal casts return strings; compare numerically.
    expect((float) $purchaseOrder->subtotal)->toBe(1000.00);
});

// Helper Method Tests
test('isDraft returns correct boolean', function () {
    $draft = PurchaseOrder::factory()->draft()->create();
    $approved = PurchaseOrder::factory()->approved()->create();

    expect($draft->isDraft())->toBeTrue()
        ->and($approved->isDraft())->toBeFalse();
});

test('canApprove checks status correctly', function () {
    $pending = PurchaseOrder::factory()->pending()->create();
    $draft = PurchaseOrder::factory()->draft()->create();

    expect($pending->canApprove())->toBeTrue()
        ->and($draft->canApprove())->toBeFalse();
});

test('isOverdue detects overdue orders', function () {
    $overdue = PurchaseOrder::factory()->overdue()->create();
    $onTime = PurchaseOrder::factory()->ordered()->create([
        'expected_delivery_date' => now()->addDays(5),
    ]);

    expect($overdue->isOverdue())->toBeTrue()
        ->and($onTime->isOverdue())->toBeFalse();
});

test('getReceivingProgress calculates correctly', function () {
    $purchaseOrder = PurchaseOrder::factory()->create();

    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity_ordered' => 100,
        'quantity_received' => 50,
    ]);

    expect($purchaseOrder->getReceivingProgress())->toBe(50.0);
});

// Scope Tests
test('draft scope returns only draft purchase orders', function () {
    PurchaseOrder::factory()->draft()->create();
    PurchaseOrder::factory()->approved()->create();

    $drafts = PurchaseOrder::draft()->get();

    expect($drafts)->toHaveCount(1)
        ->and($drafts->first()->status)->toBe(PurchaseOrderStatus::DRAFT);
});

test('overdue scope returns overdue orders', function () {
    PurchaseOrder::factory()->overdue()->create();
    PurchaseOrder::factory()->ordered()->create([
        'expected_delivery_date' => now()->addDays(5),
    ]);

    $overdue = PurchaseOrder::overdue()->get();

    expect($overdue)->toHaveCount(1);
});

// Factory State Tests
test('approved state creates approved purchase order', function () {
    $purchaseOrder = PurchaseOrder::factory()->approved()->create();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::APPROVED)
        ->and($purchaseOrder->approved_by)->not->toBeNull()
        ->and($purchaseOrder->approved_at)->not->toBeNull();
});

test('received state creates fully received purchase order', function () {
    $purchaseOrder = PurchaseOrder::factory()->received()->create();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::RECEIVED)
        ->and($purchaseOrder->actual_delivery_date)->not->toBeNull();
});

test('example', function () {
    $response = $this->get('/');

    // `/` redirects to the login screen by design.
    $response->assertRedirect(route('login'));
});
