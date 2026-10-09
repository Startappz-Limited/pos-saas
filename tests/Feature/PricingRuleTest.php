<?php

use App\Enums\PricingType;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create permissions
    Permission::create(['name' => 'pricing.view']);
    Permission::create(['name' => 'pricing.create']);
    Permission::create(['name' => 'pricing.update']);
    Permission::create(['name' => 'pricing.delete']);

    // Assign permissions to user
    $role = Role::create(['name' => 'admin']);
    $role->givePermissionTo(['pricing.view', 'pricing.create', 'pricing.update', 'pricing.delete']);
    $this->user->assignRole($role);

    $this->product = Product::factory()->create();
});

// Authorization Tests
test('authorized user can view pricing rules', function () {
    $response = $this->get(route('pricing-rules.index'));
    $response->assertOk();
});

test('unauthorized user cannot view pricing rules', function () {
    $user = staffUser();
    $this->actingAs($user);

    $response = $this->get(route('pricing-rules.index'));
    $response->assertForbidden();
});

test('authorized user can create pricing rule', function () {
    $response = $this->post(route('pricing-rules.store'), [
        'name' => 'Test Pricing Rule',
        'type' => PricingType::REGULAR->value,
        'product_id' => $this->product->id,
        'price' => 100,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('pricing_rules', [
        'name' => 'Test Pricing Rule',
        'product_id' => $this->product->id,
    ]);
});

// Validation Tests
test('pricing rule name is required', function () {
    $response = $this->post(route('pricing-rules.store'), [
        'type' => PricingType::REGULAR->value,
        'product_id' => $this->product->id,
        'price' => 100,
    ]);

    $response->assertSessionHasErrors('name');
});

test('pricing rule type is required', function () {
    $response = $this->post(route('pricing-rules.store'), [
        'name' => 'Test Rule',
        'product_id' => $this->product->id,
        'price' => 100,
    ]);

    $response->assertSessionHasErrors('type');
});

test('product is required', function () {
    $response = $this->post(route('pricing-rules.store'), [
        'name' => 'Test Rule',
        'type' => PricingType::REGULAR->value,
        'price' => 100,
    ]);

    $response->assertSessionHasErrors('product_id');
});

test('price is required', function () {
    $response = $this->post(route('pricing-rules.store'), [
        'name' => 'Test Rule',
        'type' => PricingType::REGULAR->value,
        'product_id' => $this->product->id,
    ]);

    $response->assertSessionHasErrors('price');
});

// CRUD Tests
test('can update pricing rule', function () {
    $pricingRule = PricingRule::factory()->create(['name' => 'Original Name']);

    $response = $this->put(route('pricing-rules.update', $pricingRule), [
        'name' => 'Updated Name',
        'type' => $pricingRule->type->value,
        'product_id' => $pricingRule->product_id,
        'price' => $pricingRule->price,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('pricing_rules', [
        'id' => $pricingRule->id,
        'name' => 'Updated Name',
    ]);
});

test('can delete pricing rule', function () {
    $pricingRule = PricingRule::factory()->create();

    $response = $this->delete(route('pricing-rules.destroy', $pricingRule));

    $response->assertRedirect();
    $this->assertSoftDeleted('pricing_rules', ['id' => $pricingRule->id]);
});

test('can activate pricing rule', function () {
    $pricingRule = PricingRule::factory()->inactive()->create();

    $response = $this->post(route('pricing-rules.activate', $pricingRule));

    $response->assertRedirect();
    $this->assertDatabaseHas('pricing_rules', [
        'id' => $pricingRule->id,
        'is_active' => true,
    ]);
});

test('can deactivate pricing rule', function () {
    $pricingRule = PricingRule::factory()->create();

    $response = $this->post(route('pricing-rules.deactivate', $pricingRule));

    $response->assertRedirect();
    $this->assertDatabaseHas('pricing_rules', [
        'id' => $pricingRule->id,
        'is_active' => false,
    ]);
});

// Model Tests
test('pricing rule factory creates valid pricing rule', function () {
    $pricingRule = PricingRule::factory()->create();

    expect($pricingRule->uuid)->not()->toBeEmpty()
        ->and($pricingRule->name)->not()->toBeEmpty()
        ->and($pricingRule->type)->toBeInstanceOf(PricingType::class)
        ->and($pricingRule->is_active)->toBeTrue();
});

test('regular pricing rule state works', function () {
    $pricingRule = PricingRule::factory()->regular()->create();

    expect($pricingRule->type)->toBe(PricingType::REGULAR)
        ->and($pricingRule->priority)->toBe(0);
});

test('member pricing rule state works', function () {
    $pricingRule = PricingRule::factory()->member()->create();

    expect($pricingRule->type)->toBe(PricingType::MEMBER)
        ->and($pricingRule->discount_percentage)->toBeGreaterThan(0);
});

test('bulk pricing rule state works', function () {
    $pricingRule = PricingRule::factory()->bulk()->create();

    expect($pricingRule->type)->toBe(PricingType::BULK)
        ->and($pricingRule->min_quantity)->toBeGreaterThan(0)
        ->and($pricingRule->max_quantity)->toBeGreaterThan($pricingRule->min_quantity);
});

test('promotional pricing rule state works', function () {
    $pricingRule = PricingRule::factory()->promotional()->create();

    expect($pricingRule->type)->toBe(PricingType::PROMOTIONAL)
        ->and($pricingRule->start_date)->not()->toBeNull()
        ->and($pricingRule->end_date)->not()->toBeNull();
});

// Helper Method Tests
test('isActive method returns correct boolean', function () {
    $activeRule = PricingRule::factory()->create();
    $inactiveRule = PricingRule::factory()->inactive()->create();

    expect($activeRule->isActive())->toBeTrue()
        ->and($inactiveRule->isActive())->toBeFalse();
});

test('isCurrentlyValid checks date range', function () {
    $validRule = PricingRule::factory()->create();
    $expiredRule = PricingRule::factory()->expired()->create();
    $upcomingRule = PricingRule::factory()->upcoming()->create();

    expect($validRule->isCurrentlyValid())->toBeTrue()
        ->and($expiredRule->isCurrentlyValid())->toBeFalse()
        ->and($upcomingRule->isCurrentlyValid())->toBeFalse();
});

test('isValidForQuantity checks quantity range', function () {
    $bulkRule = PricingRule::factory()->bulk()->create([
        'min_quantity' => 10,
        'max_quantity' => 100,
    ]);

    expect($bulkRule->isValidForQuantity(5))->toBeFalse()
        ->and($bulkRule->isValidForQuantity(50))->toBeTrue()
        ->and($bulkRule->isValidForQuantity(150))->toBeFalse();
});

test('calculateFinalPrice applies discounts correctly', function () {
    $rule = PricingRule::factory()->create([
        'price' => 100,
        'discount_percentage' => 20,
    ]);

    expect($rule->calculateFinalPrice(1))->toBe(80.0)
        ->and($rule->calculateFinalPrice(2))->toBe(160.0);
});

test('effective_price computed attribute works', function () {
    $rule = PricingRule::factory()->create([
        'price' => 100,
        'discount_percentage' => 25,
    ]);

    expect($rule->effective_price)->toBe(75.0);
});

// Scope Tests
test('active scope returns only active pricing rules', function () {
    PricingRule::factory()->count(3)->create();
    PricingRule::factory()->inactive()->count(2)->create();

    $activeRules = PricingRule::active()->get();

    expect($activeRules)->toHaveCount(3);
});

test('currentlyValid scope filters by date range', function () {
    PricingRule::factory()->count(2)->create(); // No date restrictions
    PricingRule::factory()->expired()->count(2)->create();
    PricingRule::factory()->upcoming()->count(1)->create();

    $validRules = PricingRule::currentlyValid()->get();

    expect($validRules)->toHaveCount(2);
});

test('forProduct scope filters by product', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();

    PricingRule::factory()->count(3)->create(['product_id' => $product1->id]);
    PricingRule::factory()->count(2)->create(['product_id' => $product2->id]);

    $product1Rules = PricingRule::forProduct($product1->id)->get();

    expect($product1Rules)->toHaveCount(3);
});

// Relationship Tests
test('pricing rule belongs to product', function () {
    $product = Product::factory()->create();
    $pricingRule = PricingRule::factory()->create(['product_id' => $product->id]);

    expect($pricingRule->product)->toBeInstanceOf(Product::class)
        ->and($pricingRule->product->id)->toBe($product->id);
});

test('pricing rule can have customer', function () {
    $customer = User::factory()->create();
    $pricingRule = PricingRule::factory()->customerSpecific()->create([
        'customer_id' => $customer->id,
    ]);

    expect($pricingRule->customer)->toBeInstanceOf(User::class)
        ->and($pricingRule->customer->id)->toBe($customer->id);
});
