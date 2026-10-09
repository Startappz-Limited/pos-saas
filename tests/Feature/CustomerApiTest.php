<?php

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);

    foreach (['customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.activate', 'customers.deactivate'] as $perm) {
        Permission::create(['name' => $perm]);
    }
    $this->user->givePermissionTo(['customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.activate', 'customers.deactivate']);
    $this->actingAs($this->user);
});

it('lists customers scoped to shop', function () {
    Customer::factory()->count(3)->create(['shop_id' => $this->shop->id]);
    $otherShop = Shop::factory()->create();
    Customer::factory()->count(2)->create(['shop_id' => $otherShop->id]);

    $response = $this->getJson('/api/customers');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data.data');
});

it('searches customers by name', function () {
    Customer::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Alice Wonder']);
    Customer::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Bob Smith']);

    $response = $this->getJson('/api/customers?search=Alice');

    $response->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.name', 'Alice Wonder');
});

it('filters customers by status', function () {
    Customer::factory()->create(['shop_id' => $this->shop->id, 'status' => 'active']);
    Customer::factory()->inactive()->create(['shop_id' => $this->shop->id]);

    $response = $this->getJson('/api/customers?status=inactive');

    $response->assertOk()
        ->assertJsonCount(1, 'data.data');
});

it('filters customers by type', function () {
    Customer::factory()->create(['shop_id' => $this->shop->id, 'customer_type' => 'retail']);
    Customer::factory()->wholesale()->create(['shop_id' => $this->shop->id]);

    $response = $this->getJson('/api/customers?customer_type=wholesale');

    $response->assertOk()
        ->assertJsonCount(1, 'data.data');
});

it('creates a customer with auto-generated code', function () {
    $response = $this->postJson('/api/customers', [
        'name' => 'New Customer',
        'email' => 'new@example.com',
        'phone' => '+254712345678',
        'customer_type' => 'retail',
        'status' => 'active',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'New Customer');

    $customer = Customer::first();
    expect($customer->code)->toStartWith('CUS-');
    expect($customer->shop_id)->toBe($this->shop->id);
    expect($customer->created_by)->toBe($this->user->id);
    expect((float) $customer->credit_balance)->toBe(0.0);
});

it('sets credit_limit to 0 when allow_credit is false', function () {
    $this->postJson('/api/customers', [
        'name' => 'No Credit Customer',
        'customer_type' => 'retail',
        'status' => 'active',
        'allow_credit' => false,
        'credit_limit' => 50000,
    ])->assertCreated();

    $customer = Customer::first();
    expect((float) $customer->credit_limit)->toBe(0.0);
});

it('creates customer with credit enabled', function () {
    $this->postJson('/api/customers', [
        'name' => 'Credit Customer',
        'customer_type' => 'wholesale',
        'status' => 'active',
        'allow_credit' => true,
        'credit_limit' => 100000,
    ])->assertCreated();

    $customer = Customer::first();
    expect($customer->allow_credit)->toBeTrue();
    expect((float) $customer->credit_limit)->toBe(100000.0);
});

it('validates required fields on create', function () {
    $this->postJson('/api/customers', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'customer_type']);
});

it('shows a customer by uuid', function () {
    $customer = Customer::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Test Customer']);

    $this->getJson("/api/customers/{$customer->uuid}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Test Customer');
});

it('updates a customer', function () {
    $customer = Customer::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Old Name']);

    $this->putJson("/api/customers/{$customer->uuid}", [
        'name' => 'New Name',
        'phone' => '+254700000000',
    ])->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.phone', '+254700000000');

    expect($customer->fresh()->updated_by)->toBe($this->user->id);
});

it('sets credit_limit to 0 when disabling credit on update', function () {
    $customer = Customer::factory()->withCredit(50000)->create(['shop_id' => $this->shop->id]);

    $this->putJson("/api/customers/{$customer->uuid}", [
        'allow_credit' => false,
    ])->assertOk();

    expect((float) $customer->fresh()->credit_limit)->toBe(0.0);
});

it('deletes a customer (soft delete)', function () {
    $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

    $this->deleteJson("/api/customers/{$customer->uuid}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Customer::find($customer->id))->toBeNull();
    expect(Customer::withTrashed()->find($customer->id))->not->toBeNull();
});

it('activates an inactive customer', function () {
    $customer = Customer::factory()->inactive()->create(['shop_id' => $this->shop->id]);

    $this->postJson("/api/customers/{$customer->uuid}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect($customer->fresh()->status)->toBe('active');
});

it('deactivates an active customer', function () {
    $customer = Customer::factory()->create(['shop_id' => $this->shop->id, 'status' => 'active']);

    $this->postJson("/api/customers/{$customer->uuid}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');

    expect($customer->fresh()->status)->toBe('inactive');
});
