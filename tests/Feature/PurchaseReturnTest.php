<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseReturnReason;
use App\Enums\PurchaseReturnStatus;
use App\Enums\StockIntakeStatus;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\StockIntake;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

function grantPurchaseReturnPermissions(User $user, array $permissions): void
{
    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission]);
    }

    $user->givePermissionTo($permissions);
}

function createCompletedStockIntake(array $overrides = []): StockIntake
{
    $supplier = $overrides['supplier'] ?? Supplier::factory()->create();
    $shop = $overrides['shop'] ?? Shop::factory()->create();
    $product = $overrides['product'] ?? Product::factory()->create([
        'supplier_id' => $supplier->id,
        'stock_quantity' => 20,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'shop_id' => $shop->id,
        'status' => PurchaseOrderStatus::RECEIVED,
    ]);

    $purchaseOrderItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'quantity_ordered' => 10,
        'quantity_received' => 10,
        'quantity_remaining' => 0,
        'unit_cost' => 5,
    ]);

    return StockIntake::factory()->completed()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'purchase_order_item_id' => $purchaseOrderItem->id,
        'supplier_id' => $supplier->id,
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'quantity_received' => 10,
        'quantity_accepted' => $overrides['quantity_accepted'] ?? 10,
        'quantity_rejected' => 0,
        'unit' => 'pcs',
    ]);
}

test('authorized user can create supplier return from completed stock intake', function () {
    grantPurchaseReturnPermissions($this->user, ['purchase_returns.create']);
    $stockIntake = createCompletedStockIntake();

    $response = $this->post(route('purchase-returns.store'), [
        'status' => PurchaseReturnStatus::PENDING->value,
        'reason' => PurchaseReturnReason::DAMAGED->value,
        'items' => [
            [
                'stock_intake_id' => $stockIntake->id,
                'purchase_order_item_id' => $stockIntake->purchase_order_item_id,
                'product_id' => $stockIntake->product_id,
                'quantity' => 3,
                'condition' => 'damaged',
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('purchase_returns', [
        'supplier_id' => $stockIntake->supplier_id,
        'shop_id' => $stockIntake->shop_id,
        'purchase_order_id' => $stockIntake->purchase_order_id,
        'status' => PurchaseReturnStatus::PENDING->value,
        'reason' => PurchaseReturnReason::DAMAGED->value,
        'total_amount' => 15,
    ]);

    $this->assertDatabaseHas('purchase_return_items', [
        'stock_intake_id' => $stockIntake->id,
        'purchase_order_item_id' => $stockIntake->purchase_order_item_id,
        'product_id' => $stockIntake->product_id,
        'quantity' => 3,
        'unit_cost' => 5,
        'line_total' => 15,
    ]);
});

test('authorized user can view supplier return index and create form', function () {
    grantPurchaseReturnPermissions($this->user, ['purchase_returns.view', 'purchase_returns.create']);
    $stockIntake = createCompletedStockIntake();

    $this->get(route('purchase-returns.index'))
        ->assertOk()
        ->assertSee('Supplier Returns');

    $this->get(route('purchase-returns.create', ['stock_intake_id' => $stockIntake->uuid]))
        ->assertOk()
        ->assertSee('Create Supplier Return')
        ->assertSee('-- Select Supplier --')
        ->assertDontSee('-- Select Shop --');
});

test('authorized user can view supplier return details', function () {
    grantPurchaseReturnPermissions($this->user, ['purchase_returns.view-all']);
    $stockIntake = createCompletedStockIntake();
    $purchaseReturn = PurchaseReturn::factory()->create([
        'purchase_order_id' => $stockIntake->purchase_order_id,
        'supplier_id' => $stockIntake->supplier_id,
        'shop_id' => $stockIntake->shop_id,
        'requested_by' => $this->user->id,
    ]);
    PurchaseReturnItem::factory()->create([
        'purchase_return_id' => $purchaseReturn->id,
        'purchase_order_item_id' => $stockIntake->purchase_order_item_id,
        'stock_intake_id' => $stockIntake->id,
        'product_id' => $stockIntake->product_id,
        'quantity' => 2,
        'unit_cost' => 5,
        'line_total' => 10,
    ]);

    $this->get(route('purchase-returns.show', $purchaseReturn))
        ->assertOk()
        ->assertSee($purchaseReturn->return_number);
});

test('supplier return cannot be created from incomplete stock intake', function () {
    grantPurchaseReturnPermissions($this->user, ['purchase_returns.create']);
    $stockIntake = createCompletedStockIntake();
    $stockIntake->update(['status' => StockIntakeStatus::PENDING]);

    $response = $this->post(route('purchase-returns.store'), [
        'status' => PurchaseReturnStatus::PENDING->value,
        'reason' => PurchaseReturnReason::DAMAGED->value,
        'items' => [
            [
                'stock_intake_id' => $stockIntake->id,
                'product_id' => $stockIntake->product_id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.stock_intake_id');
    $this->assertDatabaseCount('purchase_returns', 0);
});

test('supplier return cannot exceed accepted stock intake quantity', function () {
    grantPurchaseReturnPermissions($this->user, ['purchase_returns.create']);
    $stockIntake = createCompletedStockIntake(['quantity_accepted' => 4]);

    $response = $this->post(route('purchase-returns.store'), [
        'status' => PurchaseReturnStatus::PENDING->value,
        'reason' => PurchaseReturnReason::DAMAGED->value,
        'items' => [
            [
                'stock_intake_id' => $stockIntake->id,
                'product_id' => $stockIntake->product_id,
                'quantity' => 5,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.0.quantity');
    $this->assertDatabaseCount('purchase_returns', 0);
});

test('supplier return cannot submit the same source on multiple lines', function () {
    grantPurchaseReturnPermissions($this->user, ['purchase_returns.create']);
    $stockIntake = createCompletedStockIntake();

    $response = $this->post(route('purchase-returns.store'), [
        'status' => PurchaseReturnStatus::PENDING->value,
        'reason' => PurchaseReturnReason::DAMAGED->value,
        'items' => [
            [
                'stock_intake_id' => $stockIntake->id,
                'product_id' => $stockIntake->product_id,
                'quantity' => 1,
            ],
            [
                'stock_intake_id' => $stockIntake->id,
                'product_id' => $stockIntake->product_id,
                'quantity' => 1,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items.1.stock_intake_id');
    $this->assertDatabaseCount('purchase_returns', 0);
});

test('authorized user can approve supplier return', function () {
    grantPurchaseReturnPermissions($this->user, ['purchase_returns.approve']);
    $purchaseReturn = PurchaseReturn::factory()->create([
        'status' => PurchaseReturnStatus::PENDING,
        'requested_by' => $this->user->id,
    ]);

    $response = $this->post(route('purchase-returns.approve', $purchaseReturn));

    $response->assertRedirect();
    $purchaseReturn->refresh();

    expect($purchaseReturn->status)->toBe(PurchaseReturnStatus::APPROVED)
        ->and($purchaseReturn->approved_by)->toBe($this->user->id)
        ->and($purchaseReturn->approved_at)->not->toBeNull();
});

test('shipping approved supplier return deducts stock and records movement', function () {
    grantPurchaseReturnPermissions($this->user, ['purchase_returns.ship']);
    $supplier = Supplier::factory()->create();
    $shop = Shop::factory()->create();
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'stock_quantity' => 10,
    ]);
    $purchaseReturn = PurchaseReturn::factory()->approved()->create([
        'supplier_id' => $supplier->id,
        'shop_id' => $shop->id,
        'requested_by' => $this->user->id,
    ]);

    PurchaseReturnItem::factory()->create([
        'purchase_return_id' => $purchaseReturn->id,
        'product_id' => $product->id,
        'quantity' => 4,
        'unit_cost' => 7,
        'line_total' => 28,
    ]);

    $response = $this->post(route('purchase-returns.ship', $purchaseReturn), [
        'shipment_reference' => 'WAYBILL-123',
    ]);

    $response->assertRedirect();

    expect($purchaseReturn->fresh()->status)->toBe(PurchaseReturnStatus::SHIPPED)
        ->and($product->fresh()->stock_quantity)->toBe(6);

    $this->assertDatabaseHas('stock_movements', [
        'shop_id' => $shop->id,
        'product_id' => $product->id,
        'movement_type' => StockMovementType::SUPPLIER_RETURN->value,
        'quantity' => 4,
        'quantity_before' => 10,
        'quantity_after' => 6,
        'reference_uuid' => $purchaseReturn->uuid,
    ]);
});

test('full access user can approve and return to supplier in one action', function () {
    grantPurchaseReturnPermissions($this->user, ['purchase_returns.full-access']);
    $supplier = Supplier::factory()->create();
    $shop = Shop::factory()->create();
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'stock_quantity' => 10,
    ]);
    $purchaseReturn = PurchaseReturn::factory()->create([
        'supplier_id' => $supplier->id,
        'shop_id' => $shop->id,
        'status' => PurchaseReturnStatus::PENDING,
        'requested_by' => $this->user->id,
    ]);

    PurchaseReturnItem::factory()->create([
        'purchase_return_id' => $purchaseReturn->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_cost' => 7,
        'line_total' => 21,
    ]);

    $response = $this->post(route('purchase-returns.approveAndShip', $purchaseReturn));

    $response->assertRedirect();
    $purchaseReturn->refresh();

    expect($purchaseReturn->status)->toBe(PurchaseReturnStatus::SHIPPED)
        ->and($purchaseReturn->approved_by)->toBe($this->user->id)
        ->and($purchaseReturn->shipped_by)->toBe($this->user->id)
        ->and($purchaseReturn->approved_at)->not->toBeNull()
        ->and($purchaseReturn->shipped_at)->not->toBeNull()
        ->and($product->fresh()->stock_quantity)->toBe(7);
});

test('completed workflow action buttons are hidden for full access user', function () {
    grantPurchaseReturnPermissions($this->user, ['purchase_returns.full-access']);
    $purchaseReturn = PurchaseReturn::factory()->shipped()->create([
        'requested_by' => $this->user->id,
    ]);

    $this->get(route('purchase-returns.show', $purchaseReturn))
        ->assertOk()
        ->assertDontSee(route('purchase-returns.approveAndShip', $purchaseReturn), false)
        ->assertDontSee(route('purchase-returns.approve', $purchaseReturn), false)
        ->assertDontSee(route('purchase-returns.ship', $purchaseReturn), false)
        ->assertDontSee(route('purchase-returns.cancel', $purchaseReturn), false)
        ->assertSee(route('purchase-returns.complete', $purchaseReturn), false);
});

test('customer return item marked for supplier return can become supplier return item', function () {
    grantPurchaseReturnPermissions($this->user, ['purchase_returns.create']);
    $supplier = Supplier::factory()->create();
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create();
    $product = Product::factory()->create([
        'supplier_id' => $supplier->id,
        'stock_quantity' => 5,
    ]);
    $sale = Sale::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id]);
    $saleItem = SaleItem::create([
        'uuid' => (string) Str::uuid(),
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 20,
        'discount_amount' => 0,
        'line_total' => 40,
        'unit_cost' => 8,
        'total_cost' => 16,
        'profit' => 24,
        'profit_margin' => 60,
        'status' => 'completed',
    ]);
    $saleReturn = SaleReturn::factory()->create([
        'sale_id' => $sale->id,
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'requested_by' => $this->user->id,
    ]);
    $returnItem = ReturnItem::create([
        'return_id' => $saleReturn->id,
        'sale_item_id' => $saleItem->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 20,
        'total_price' => 40,
        'condition' => 'damaged',
        'is_restockable' => false,
        'is_restocked' => false,
        'return_to_supplier' => true,
        'return_to_supplier_at' => now(),
        'return_to_supplier_notes' => 'Customer returned damaged item.',
    ]);

    $response = $this->post(route('purchase-returns.store'), [
        'status' => PurchaseReturnStatus::PENDING->value,
        'reason' => PurchaseReturnReason::CUSTOMER_RETURN->value,
        'items' => [
            [
                'return_item_id' => $returnItem->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'condition' => $returnItem->condition,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('purchase_returns', [
        'supplier_id' => $supplier->id,
        'shop_id' => $shop->id,
        'sale_return_id' => $saleReturn->id,
        'reason' => PurchaseReturnReason::CUSTOMER_RETURN->value,
        'total_amount' => 16,
    ]);

    $this->assertDatabaseHas('purchase_return_items', [
        'return_item_id' => $returnItem->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_cost' => 8,
        'line_total' => 16,
    ]);

    expect($returnItem->fresh()->return_to_supplier)->toBeTrue()
        ->and($returnItem->fresh()->return_to_supplier_at)->not->toBeNull();
});
