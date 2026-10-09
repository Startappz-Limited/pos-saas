<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockIntakeStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockIntake;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// Authorization Tests
test('authorized user can view stock intakes', function () {
    Permission::create(['name' => 'stock_intakes.view']);
    $this->user->givePermissionTo('stock_intakes.view');

    StockIntake::factory()->count(3)->create();

    $response = $this->get(route('stock-intakes.index'));

    $response->assertOk();
});

test('unauthorized user cannot view stock intakes', function () {
    $response = $this->get(route('stock-intakes.index'));

    $response->assertForbidden();
});

test('authorized user can create stock intake', function () {
    Permission::create(['name' => 'stock_intakes.create']);
    $this->user->givePermissionTo('stock_intakes.create');

    $purchaseOrder = PurchaseOrder::factory()->ordered()->create();
    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
    ]);

    $data = [
        'purchase_order_item_id' => $item->id,
        'intake_date' => now()->toDateString(),
        'quantity_received' => 50,
        'quantity_accepted' => 48,
        'quantity_rejected' => 2,
    ];

    $response = $this->post(route('stock-intakes.store'), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('stock_intakes', [
        'purchase_order_item_id' => $item->id,
        'quantity_received' => 50,
    ]);
});

// Validation Tests
test('manual stock intake requires product and shop', function () {
    Permission::create(['name' => 'stock_intakes.create']);
    $this->user->givePermissionTo('stock_intakes.create');

    $data = [
        'intake_date' => now()->toDateString(),
        'quantity_received' => 50,
        'quantity_accepted' => 50,
        'quantity_rejected' => 0,
    ];

    $response = $this->post(route('stock-intakes.store'), $data);

    $response->assertSessionHasErrors(['product_id', 'shop_id']);
});

test('quantity received is required', function () {
    Permission::create(['name' => 'stock_intakes.create']);
    $this->user->givePermissionTo('stock_intakes.create');

    $item = PurchaseOrderItem::factory()->create();

    $data = [
        'purchase_order_item_id' => $item->id,
        'intake_date' => now()->toDateString(),
        'quantity_accepted' => 50,
        'quantity_rejected' => 0,
    ];

    $response = $this->post(route('stock-intakes.store'), $data);

    $response->assertSessionHasErrors('quantity_received');
});

test('expiry date must be in future', function () {
    Permission::create(['name' => 'stock_intakes.create']);
    $this->user->givePermissionTo('stock_intakes.create');

    $item = PurchaseOrderItem::factory()->create();

    $data = [
        'purchase_order_item_id' => $item->id,
        'intake_date' => now()->toDateString(),
        'quantity_received' => 50,
        'quantity_accepted' => 50,
        'quantity_rejected' => 0,
        'expiry_date' => now()->subDay()->toDateString(),
    ];

    $response = $this->post(route('stock-intakes.store'), $data);

    $response->assertSessionHasErrors('expiry_date');
});

// CRUD Tests
test('can update stock intake', function () {
    Permission::create(['name' => 'stock_intakes.update']);
    $this->user->givePermissionTo('stock_intakes.update');

    $stockIntake = StockIntake::factory()->pending()->create();

    $data = [
        'intake_date' => $stockIntake->intake_date->toDateString(),
        'quantity_received' => $stockIntake->quantity_received,
        'quantity_accepted' => $stockIntake->quantity_accepted,
        'quantity_rejected' => $stockIntake->quantity_rejected,
        'quality_notes' => 'Updated quality notes',
    ];

    $response = $this->put(route('stock-intakes.update', $stockIntake), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('stock_intakes', [
        'id' => $stockIntake->id,
        'quality_notes' => 'Updated quality notes',
    ]);
});

test('can delete pending stock intake', function () {
    Permission::create(['name' => 'stock_intakes.delete']);
    $this->user->givePermissionTo('stock_intakes.delete');

    $stockIntake = StockIntake::factory()->pending()->create();

    $response = $this->delete(route('stock-intakes.destroy', $stockIntake));

    $response->assertRedirect();
    $this->assertSoftDeleted('stock_intakes', ['id' => $stockIntake->id]);
});

test('can complete stock intake', function () {
    Permission::create(['name' => 'stock_intakes.complete']);
    $this->user->givePermissionTo('stock_intakes.complete');

    $product = Product::factory()->create(['stock_quantity' => 100]);
    $stockIntake = StockIntake::factory()->pending()->create([
        'product_id' => $product->id,
        'quantity_accepted' => 50,
    ]);

    $response = $this->post(route('stock-intakes.complete', $stockIntake));

    $response->assertRedirect();
    $stockIntake->refresh();
    $product->refresh();

    expect($stockIntake->status)->toBe(StockIntakeStatus::COMPLETED)
        ->and((float) $product->stock_quantity)->toBe(150.0);
});

test('can cancel stock intake', function () {
    Permission::create(['name' => 'stock_intakes.update']);
    $this->user->givePermissionTo('stock_intakes.update');

    $stockIntake = StockIntake::factory()->pending()->create();

    $response = $this->post(route('stock-intakes.cancel', $stockIntake));

    $response->assertRedirect();
    $stockIntake->refresh();
    expect($stockIntake->status)->toBe(StockIntakeStatus::CANCELLED);
});

// Model Tests
test('stock intake factory creates valid stock intake', function () {
    $stockIntake = StockIntake::factory()->create();

    expect($stockIntake)->toBeInstanceOf(StockIntake::class)
        ->and($stockIntake->uuid)->not->toBeNull()
        ->and($stockIntake->intake_number)->toStartWith('SI-');
});

test('stock intake has product relationship', function () {
    $product = Product::factory()->create();
    $stockIntake = StockIntake::factory()->create([
        'product_id' => $product->id,
    ]);

    expect($stockIntake->product)->toBeInstanceOf(Product::class)
        ->and($stockIntake->product->id)->toBe($product->id);
});

// Helper Method Tests
test('isPending returns correct boolean', function () {
    $pending = StockIntake::factory()->pending()->create();
    $completed = StockIntake::factory()->completed()->create();

    expect($pending->isPending())->toBeTrue()
        ->and($completed->isPending())->toBeFalse();
});

test('canComplete checks status correctly', function () {
    $pending = StockIntake::factory()->pending()->create();
    $completed = StockIntake::factory()->completed()->create();

    expect($pending->canComplete())->toBeTrue()
        ->and($completed->canComplete())->toBeFalse();
});

test('hasQualityIssues detects rejected items', function () {
    $withIssues = StockIntake::factory()->withQualityIssues()->create();
    $excellent = StockIntake::factory()->excellentQuality()->create();

    expect($withIssues->hasQualityIssues())->toBeTrue()
        ->and($excellent->hasQualityIssues())->toBeFalse();
});

test('getAcceptanceRate calculates correctly', function () {
    $stockIntake = StockIntake::factory()->create([
        'quantity_received' => 100,
        'quantity_accepted' => 90,
        'quantity_rejected' => 10,
    ]);

    expect($stockIntake->getAcceptanceRate())->toBe(90.0);
});

test('getRejectionRate calculates correctly', function () {
    $stockIntake = StockIntake::factory()->create([
        'quantity_received' => 100,
        'quantity_accepted' => 85,
        'quantity_rejected' => 15,
    ]);

    expect($stockIntake->getRejectionRate())->toBe(15.0);
});

// Scope Tests
test('pending scope returns only pending stock intakes', function () {
    StockIntake::factory()->pending()->create();
    StockIntake::factory()->completed()->create();

    $pending = StockIntake::pending()->get();

    expect($pending)->toHaveCount(1)
        ->and($pending->first()->status)->toBe(StockIntakeStatus::PENDING);
});

test('withQualityIssues scope returns items with rejections', function () {
    StockIntake::factory()->withQualityIssues()->create();
    StockIntake::factory()->excellentQuality()->create();

    $withIssues = StockIntake::withQualityIssues()->get();

    expect($withIssues)->toHaveCount(1)
        ->and($withIssues->first()->quantity_rejected)->toBeGreaterThan(0);
});

// Factory State Tests
test('completed state creates completed stock intake', function () {
    $stockIntake = StockIntake::factory()->completed()->create();

    expect($stockIntake->status)->toBe(StockIntakeStatus::COMPLETED)
        ->and($stockIntake->completed_by)->not->toBeNull()
        ->and($stockIntake->completed_at)->not->toBeNull();
});

test('withQualityIssues state creates intake with rejections', function () {
    $stockIntake = StockIntake::factory()->withQualityIssues()->create();

    expect($stockIntake->quantity_rejected)->toBeGreaterThan(0)
        ->and($stockIntake->quality_status)->toBeIn(['poor', 'rejected']);
});

test('excellentQuality state creates intake with no rejections', function () {
    $stockIntake = StockIntake::factory()->excellentQuality()->create();

    // decimal casts return strings; compare numerically.
    expect((float) $stockIntake->quantity_rejected)->toBe(0.0)
        ->and((float) $stockIntake->quantity_accepted)->toBe((float) $stockIntake->quantity_received)
        ->and($stockIntake->quality_status)->toBe('excellent');
});

test('example', function () {
    $response = $this->get('/');

    // `/` redirects to the login screen by design.
    $response->assertRedirect(route('login'));
});

// PO ↔ Stock Intake Linking Tests
test('creating stock intake from PO item links them correctly', function () {
    Permission::create(['name' => 'stock_intakes.create']);
    $this->user->givePermissionTo('stock_intakes.create');

    $purchaseOrder = PurchaseOrder::factory()->ordered()->create();
    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity_ordered' => 100,
        'quantity_received' => 0,
        'quantity_remaining' => 100,
    ]);

    $data = [
        'purchase_order_item_id' => $item->id,
        'quantity_received' => 50,
        'quantity_accepted' => 48,
        'quantity_rejected' => 2,
    ];

    $response = $this->post(route('stock-intakes.store'), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('stock_intakes', [
        'purchase_order_id' => $purchaseOrder->id,
        'purchase_order_item_id' => $item->id,
        'supplier_id' => $purchaseOrder->supplier_id,
        'shop_id' => $purchaseOrder->shop_id,
        'product_id' => $item->product_id,
    ]);
});

test('completing stock intake updates PO status to partially received', function () {
    Permission::create(['name' => 'stock_intakes.complete']);
    $this->user->givePermissionTo('stock_intakes.complete');

    $purchaseOrder = PurchaseOrder::factory()->ordered()->create();
    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity_ordered' => 100,
        'quantity_received' => 0,
        'quantity_remaining' => 100,
    ]);

    $stockIntake = StockIntake::factory()->pending()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'purchase_order_item_id' => $item->id,
        'product_id' => $item->product_id,
        'quantity_accepted' => 50,
    ]);

    $this->post(route('stock-intakes.complete', $stockIntake));

    $purchaseOrder->refresh();
    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED);
});

test('completing all items sets PO status to received', function () {
    Permission::create(['name' => 'stock_intakes.complete']);
    $this->user->givePermissionTo('stock_intakes.complete');

    $purchaseOrder = PurchaseOrder::factory()->ordered()->create();
    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity_ordered' => 50,
        'quantity_received' => 0,
        'quantity_remaining' => 50,
    ]);

    $stockIntake = StockIntake::factory()->pending()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'purchase_order_item_id' => $item->id,
        'product_id' => $item->product_id,
        'quantity_accepted' => 50,
    ]);

    $this->post(route('stock-intakes.complete', $stockIntake));

    $purchaseOrder->refresh();
    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::RECEIVED);
});

test('cannot receive more than remaining quantity on PO item', function () {
    Permission::create(['name' => 'stock_intakes.create']);
    $this->user->givePermissionTo('stock_intakes.create');

    $purchaseOrder = PurchaseOrder::factory()->ordered()->create();
    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'quantity_ordered' => 100,
        'quantity_received' => 80,
        'quantity_remaining' => 20,
    ]);

    $data = [
        'purchase_order_item_id' => $item->id,
        'quantity_received' => 50,
        'quantity_accepted' => 50,
        'quantity_rejected' => 0,
    ];

    $response = $this->post(route('stock-intakes.store'), $data);

    $response->assertSessionHasErrors('quantity_received');
});

test('accepted plus rejected cannot exceed received', function () {
    Permission::create(['name' => 'stock_intakes.create']);
    $this->user->givePermissionTo('stock_intakes.create');

    $product = Product::factory()->create();
    $shop = Shop::factory()->create();

    $data = [
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'quantity_received' => 50,
        'quantity_accepted' => 40,
        'quantity_rejected' => 20,
    ];

    $response = $this->post(route('stock-intakes.store'), $data);

    $response->assertSessionHasErrors('quantity_accepted');
});

test('manual intake without PO creates successfully', function () {
    Permission::create(['name' => 'stock_intakes.create']);
    $this->user->givePermissionTo('stock_intakes.create');

    $product = Product::factory()->create();
    $shop = Shop::factory()->create();
    $supplier = Supplier::factory()->create();

    $data = [
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'supplier_id' => $supplier->id,
        'quantity_received' => 100,
        'quantity_accepted' => 100,
        'quantity_rejected' => 0,
        'unit' => 'pcs',
    ];

    $response = $this->post(route('stock-intakes.store'), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('stock_intakes', [
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'purchase_order_id' => null,
    ]);
});
