<?php

use App\Models\ExpenseCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TwoBusinesses;

/**
 * exists: rules respect the tenant scopes (stage 1a, TenantPresenceVerifier):
 * another business's ids, and shops the user can't access, fail validation.
 */
beforeEach(function () {
    $this->fixture = TwoBusinesses::create();
});

/**
 * Whether $value passes `exists:{$table},id` for the current viewer.
 */
function existsFor(string $table, mixed $value): bool
{
    return Validator::make(['id' => $value], ['id' => "exists:{$table},id"])->passes();
}

it('accepts the business\'s own ids and refuses another business\'s', function () {
    $a = $this->fixture->a;
    $b = $this->fixture->b;
    test()->actingAs($a->owner);
    app(TenantContext::class)->set($a->business);

    expect(existsFor('shops', $a->shops[1]->id))->toBeTrue()
        ->and(existsFor('shops', $b->shops[0]->id))->toBeFalse()
        ->and(existsFor('customers', $a->customer->id))->toBeTrue()
        ->and(existsFor('customers', $b->customer->id))->toBeFalse()
        ->and(existsFor('products', $b->product->id))->toBeFalse()
        ->and(existsFor('suppliers', $b->supplier->id))->toBeFalse()
        ->and(existsFor('sales', $b->sale->id))->toBeFalse();
});

it('refuses a shop of the same business the user can\'t access', function () {
    $a = $this->fixture->a;
    test()->actingAs($a->cashier);
    app(TenantContext::class)->set($a->business);

    expect(existsFor('shops', $a->shops[0]->id))->toBeTrue()
        ->and(existsFor('shops', $a->shops[1]->id))->toBeFalse()
        ->and(existsFor('sales', $a->sale->id))->toBeFalse();
});

it('accepts only members of the business as users', function () {
    app(TenantContext::class)->set($this->fixture->a->business);

    expect(existsFor('users', $this->fixture->a->cashier->id))->toBeTrue()
        ->and(existsFor('users', $this->fixture->both->id))->toBeTrue()
        ->and(existsFor('users', $this->fixture->b->cashier->id))->toBeFalse();
});

it('checks child rows through their parent', function () {
    $itemInB = app(TenantContext::class)->run($this->fixture->b->business, fn () => PurchaseOrderItem::factory()->create([
        'purchase_order_id' => PurchaseOrder::factory()->create(['shop_id' => $this->fixture->b->shops[0]->id])->id,
    ]));
    app(TenantContext::class)->set($this->fixture->a->business);

    expect(existsFor('purchase_order_items', $itemInB->id))->toBeFalse();
});

it('still counts a soft-deleted record, as before', function () {
    $a = $this->fixture->a;
    app(TenantContext::class)->set($a->business);
    $a->supplier->delete();

    expect(existsFor('suppliers', $a->supplier->id))->toBeTrue();
});

it('checks a platform table as before', function () {
    app(TenantContext::class)->set($this->fixture->a->business);
    $permission = DB::table('permissions')->insertGetId(['name' => 'anything.view', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);

    expect(existsFor('permissions', $permission))->toBeTrue();
});

describe('requests', function () {
    beforeEach(fn () => app(TenantContext::class)->forget());

    it('refuses a sale of another business\'s product', function () {
        $a = $this->fixture->a;
        Sanctum::actingAs($a->owner, ['*']);

        $this->postJson('/api/sales', [
            'shop_id' => $a->shops[0]->id,
            'source_id' => DB::table('sale_sources')->where('business_id', $a->business->id)->value('id'),
            'delivery_location' => 'In store',
            'walk_in_customer_name' => 'Walk In',
            'walk_in_customer_phone' => '0700000000',
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->fixture->b->product->id, 'quantity' => 1, 'price' => 100]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
    });

    it('refuses an expense in another business\'s category', function () {
        $a = $this->fixture->a;
        $categoryInB = app(TenantContext::class)->run($this->fixture->b->business, fn () => ExpenseCategory::factory()->create());
        Sanctum::actingAs($a->owner, ['*']);

        $this->postJson('/api/expenses', [
            'category_id' => $categoryInB->id,
            'title' => 'Rent',
            'amount' => 100,
            'expense_date' => today()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('category_id');
    });

    it('refuses a sale for a member who names another business\'s customer', function () {
        $both = $this->fixture->both;
        Sanctum::actingAs($both, ['*']);

        // Acting for A (the default business), B's customer doesn't exist.
        $this->postJson('/api/sales', [
            'shop_id' => $this->fixture->a->shops[0]->id,
            'customer_id' => $this->fixture->b->customer->id,
            'payment_method' => 'cash',
            'items' => [['product_id' => $this->fixture->a->product->id, 'quantity' => 1, 'price' => 100]],
        ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');
    });
});
