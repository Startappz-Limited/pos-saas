<?php

use App\Enums\SupplierStatus;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create permissions
    Permission::create(['name' => 'suppliers.view']);
    Permission::create(['name' => 'suppliers.create']);
    Permission::create(['name' => 'suppliers.update']);
    Permission::create(['name' => 'suppliers.delete']);

    // Assign permissions to user
    $role = Role::create(['name' => 'admin']);
    $role->givePermissionTo(['suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete']);
    $this->user->assignRole($role);
});

// Authorization Tests
test('authorized user can view suppliers', function () {
    $response = $this->get(route('suppliers.index'));
    $response->assertOk();
});

test('unauthorized user cannot view suppliers', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('suppliers.index'));
    $response->assertForbidden();
});

test('authorized user can create supplier', function () {
    $response = $this->post(route('suppliers.store'), [
        'name' => 'Test Supplier',
        'status' => SupplierStatus::ACTIVE->value,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('suppliers', [
        'name' => 'Test Supplier',
    ]);
});

test('blank supplier currency defaults to configured currency', function () {
    config(['app.currency_code' => 'KES']);

    $response = $this->post(route('suppliers.store'), [
        'name' => 'Blank Currency Supplier',
        'currency' => '',
        'status' => SupplierStatus::ACTIVE->value,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('suppliers', [
        'name' => 'Blank Currency Supplier',
        'currency' => 'KES',
    ]);
});

// Validation Tests
test('supplier name is required', function () {
    $response = $this->post(route('suppliers.store'), [
        'status' => SupplierStatus::ACTIVE->value,
    ]);

    $response->assertSessionHasErrors('name');
});

test('supplier status is required', function () {
    $response = $this->post(route('suppliers.store'), [
        'name' => 'Test Supplier',
    ]);

    $response->assertSessionHasErrors('status');
});

test('supplier code must be unique', function () {
    Supplier::factory()->create(['code' => 'SUP-DUPLICATE']);

    $response = $this->post(route('suppliers.store'), [
        'name' => 'Test Supplier',
        'code' => 'SUP-DUPLICATE',
        'status' => SupplierStatus::ACTIVE->value,
    ]);

    $response->assertSessionHasErrors('code');
});

test('email must be valid format', function () {
    $response = $this->post(route('suppliers.store'), [
        'name' => 'Test Supplier',
        'email' => 'invalid-email',
        'status' => SupplierStatus::ACTIVE->value,
    ]);

    $response->assertSessionHasErrors('email');
});

// CRUD Tests
test('can update supplier', function () {
    $supplier = Supplier::factory()->create(['name' => 'Original Name']);

    $response = $this->put(route('suppliers.update', $supplier), [
        'name' => 'Updated Name',
        'status' => $supplier->status->value,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'name' => 'Updated Name',
    ]);
});

test('blank supplier currency does not clear existing currency on update', function () {
    $supplier = Supplier::factory()->create(['currency' => 'USD']);

    $response = $this->put(route('suppliers.update', $supplier), [
        'name' => 'Updated Name',
        'currency' => '',
        'status' => $supplier->status->value,
    ]);

    $response->assertRedirect();
    expect($supplier->fresh()->currency)->toBe('USD');
});

test('can delete supplier', function () {
    $supplier = Supplier::factory()->create();

    $response = $this->delete(route('suppliers.destroy', $supplier));

    $response->assertRedirect();
    $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
});

test('can activate supplier', function () {
    $supplier = Supplier::factory()->inactive()->create();

    $response = $this->post(route('suppliers.activate', $supplier));

    $response->assertRedirect();
    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'status' => SupplierStatus::ACTIVE->value,
    ]);
});

test('can deactivate supplier', function () {
    $supplier = Supplier::factory()->active()->create();

    $response = $this->post(route('suppliers.deactivate', $supplier));

    $response->assertRedirect();
    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'status' => SupplierStatus::INACTIVE->value,
    ]);
});

test('can suspend supplier', function () {
    $supplier = Supplier::factory()->active()->create();

    $response = $this->post(route('suppliers.suspend', $supplier));

    $response->assertRedirect();
    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'status' => SupplierStatus::SUSPENDED->value,
    ]);
});

test('can blacklist supplier', function () {
    $supplier = Supplier::factory()->active()->create();

    $response = $this->post(route('suppliers.blacklist', $supplier));

    $response->assertRedirect();
    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'status' => SupplierStatus::BLACKLISTED->value,
    ]);
});

// Model Tests
test('supplier factory creates valid supplier', function () {
    $supplier = Supplier::factory()->create();

    expect($supplier->uuid)->not()->toBeEmpty()
        ->and($supplier->name)->not()->toBeEmpty()
        ->and($supplier->code)->not()->toBeEmpty()
        ->and($supplier->status)->toBe(SupplierStatus::ACTIVE);
});

test('supplier inactive state works', function () {
    $supplier = Supplier::factory()->inactive()->create();

    expect($supplier->status)->toBe(SupplierStatus::INACTIVE)
        ->and($supplier->isActive())->toBeFalse();
});

test('supplier suspended state works', function () {
    $supplier = Supplier::factory()->suspended()->create();

    expect($supplier->status)->toBe(SupplierStatus::SUSPENDED)
        ->and($supplier->isSuspended())->toBeTrue();
});

test('supplier blacklisted state works', function () {
    $supplier = Supplier::factory()->blacklisted()->create();

    expect($supplier->status)->toBe(SupplierStatus::BLACKLISTED)
        ->and($supplier->isBlacklisted())->toBeTrue();
});

// Helper Method Tests
test('isActive method returns correct boolean', function () {
    $activeSupplier = Supplier::factory()->active()->create();
    $inactiveSupplier = Supplier::factory()->inactive()->create();

    expect($activeSupplier->isActive())->toBeTrue()
        ->and($inactiveSupplier->isActive())->toBeFalse();
});

test('canPlaceOrders checks supplier status', function () {
    $activeSupplier = Supplier::factory()->active()->create();
    $suspendedSupplier = Supplier::factory()->suspended()->create();
    $blacklistedSupplier = Supplier::factory()->blacklisted()->create();

    expect($activeSupplier->canPlaceOrders())->toBeTrue()
        ->and($suspendedSupplier->canPlaceOrders())->toBeFalse()
        ->and($blacklistedSupplier->canPlaceOrders())->toBeFalse();
});

test('hasReachedCreditLimit checks balance against limit', function () {
    $supplierWithinLimit = Supplier::factory()->create([
        'credit_limit' => 10000,
        'current_balance' => 5000,
    ]);

    $supplierAtLimit = Supplier::factory()->reachedCreditLimit()->create();

    expect($supplierWithinLimit->hasReachedCreditLimit())->toBeFalse()
        ->and($supplierAtLimit->hasReachedCreditLimit())->toBeTrue();
});

test('getRemainingCredit calculates correctly', function () {
    $supplier = Supplier::factory()->create([
        'credit_limit' => 10000,
        'current_balance' => 3000,
    ]);

    expect($supplier->getRemainingCredit())->toBe(7000.0);
});

test('getDeliverySuccessRate calculates correctly', function () {
    $supplier = Supplier::factory()->create([
        'on_time_deliveries' => 80,
        'late_deliveries' => 20,
    ]);

    expect($supplier->getDeliverySuccessRate())->toBe(80.0);
});

test('getAverageOrderValue calculates correctly', function () {
    $supplier = Supplier::factory()->create([
        'total_orders' => 50000,
        'order_count' => 10,
    ]);

    expect($supplier->getAverageOrderValue())->toBe(5000.0);
});

// Scope Tests
test('active scope returns only active suppliers', function () {
    Supplier::factory()->active()->count(3)->create();
    Supplier::factory()->inactive()->count(2)->create();

    $activeSuppliers = Supplier::active()->get();

    expect($activeSuppliers)->toHaveCount(3);
});

test('suspended scope returns only suspended suppliers', function () {
    Supplier::factory()->active()->count(2)->create();
    Supplier::factory()->suspended()->count(3)->create();

    $suspendedSuppliers = Supplier::suspended()->get();

    expect($suspendedSuppliers)->toHaveCount(3);
});

test('nearCreditLimit scope filters correctly', function () {
    Supplier::factory()->create([
        'credit_limit' => 10000,
        'current_balance' => 5000,
    ]);

    Supplier::factory()->nearCreditLimit()->count(2)->create();

    $nearLimit = Supplier::nearCreditLimit()->get();

    expect($nearLimit)->toHaveCount(2);
});

// Factory State Tests
test('withOrders state populates order data', function () {
    $supplier = Supplier::factory()->withOrders()->create();

    expect($supplier->total_orders)->toBeGreaterThan(0)
        ->and($supplier->order_count)->toBeGreaterThan(0)
        ->and($supplier->average_rating)->toBeGreaterThan(0);
});

test('withGoodDeliveryRecord state creates reliable supplier', function () {
    $supplier = Supplier::factory()->withGoodDeliveryRecord()->create();

    expect($supplier->on_time_deliveries)->toBeGreaterThan($supplier->late_deliveries)
        ->and($supplier->average_rating)->toBeGreaterThanOrEqual(4.0);
});
