<?php

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Database\ColumnDefaults;
use Illuminate\Database\QueryException;
use Laravel\Sanctum\Sanctum;

/**
 * A blank optional field means "use the default" (reported 21 October 2026:
 * creating a shop with no country failed with "Column 'country' cannot be
 * null"). Laravel turns an empty field into null; a null in a column that
 * can't be null but has a default now becomes that default instead of the
 * database refusing the row. Checked on both engines, which report defaults
 * differently.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
});

it('creates a shop with the optional fields left blank, as the form sends them', function () {
    $this->actingAs($this->owner)->post(route('shops.store'), [
        'name' => 'Westlands Store', 'code' => 'SHOP-001',
        'description' => '', 'phone' => '', 'email' => '', 'address' => '', 'city' => '', 'state' => '',
        'country' => '', 'postal_code' => '', 'status' => '', 'tax_pin' => '', 'vat_registered' => '',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $shop = Shop::where('code', 'SHOP-001')->firstOrFail();
    expect($shop->country)->toBe('Kenya')
        ->and($shop->status->value ?? $shop->status)->toBe('active')
        ->and((bool) $shop->vat_registered)->toBeFalse()
        ->and($shop->city)->toBeNull();
});

it('puts the default back when an optional field is cleared on an existing record', function () {
    $shop = Shop::factory()->create(['country' => 'Uganda']);

    $shop->update(['country' => null]);

    expect($shop->fresh()->country)->toBe('Kenya');
});

it('uses each column\'s default for a null on other tables', function () {
    $product = Product::factory()->create(['unit' => null, 'reorder_level' => null, 'track_stock' => null]);
    $supplier = Supplier::factory()->create(['currency' => null, 'payment_terms_days' => null]);
    $category = Category::factory()->create(['order' => null]);
    $order = PurchaseOrder::factory()->create(['shipping_cost' => null, 'discount_amount' => null]);

    expect($product->fresh()->unit)->toBe('piece')
        ->and($product->fresh()->reorder_level)->toBe(10)
        ->and((bool) $product->fresh()->track_stock)->toBeTrue()
        // The model's own default (the app's currency) wins over the column's (USD).
        ->and($supplier->fresh()->currency)->toBe(config('app.currency_code', 'KES'))
        ->and((int) $supplier->fresh()->payment_terms_days)->toBe(30)
        ->and((int) $category->fresh()->order)->toBe(0)
        ->and((float) $order->fresh()->shipping_cost)->toBe(0.0)
        ->and((float) $order->fresh()->discount_amount)->toBe(0.0);
});

it('keeps a value that was given', function () {
    expect(Shop::factory()->create(['country' => 'Tanzania'])->fresh()->country)->toBe('Tanzania');
});

it('still refuses a missing value that has no default', function () {
    expect(fn () => Shop::factory()->create(['name' => null]))->toThrow(QueryException::class);
});

it('reads defaults the way each engine reports them', function (string $reported, ?string $value) {
    expect(ColumnDefaults::literal($reported))->toBe($value);
})->with([
    'MySQL 8 string' => ['Kenya', 'Kenya'],
    'quoted string (MariaDB, SQLite)' => ["'Kenya'", 'Kenya'],
    'quoted with a quote' => ["'O''Brien'", "O'Brien"],
    'integer' => ['10', '10'],
    'decimal' => ['0.00', '0.00'],
    'boolean word' => ['false', '0'],
    'empty string' => ["''", ''],
    'timestamp expression' => ['CURRENT_TIMESTAMP', null],
    'function' => ['uuid()', null],
    'MariaDB null' => ['NULL', null],
]);

it('shows "required" under a field that has no default, instead of reaching the database', function () {
    $this->actingAs($this->owner)->from(route('shops.create'))
        ->post(route('shops.store'), ['name' => '', 'code' => ''])
        ->assertRedirect(route('shops.create'))
        ->assertSessionHasErrors(['name', 'code']);
    $message = session('errors')->first('name');

    expect(Shop::count())->toBe(0)
        ->and($message)->toContain('required');

    // The form, shown again, marks the field and prints its message under it.
    $html = $this->actingAs($this->owner)->get(route('shops.create'))->getContent();
    expect($html)->toMatch('/name="name"[^>]*\bis-invalid\b|\bis-invalid\b[^>]*name="name"/')
        ->and($html)->toContain(e($message));
});

it('refuses a blank or unknown expense category status with a message, on the web and the API', function () {
    $category = ExpenseCategory::factory()->create();

    $this->actingAs($this->owner)->from(route('expense-categories.edit', $category))
        ->put(route('expense-categories.update', $category), ['name' => 'Rent', 'status' => ''])
        ->assertSessionHasErrors(['status' => 'The status field is required.']);
    $this->actingAs($this->owner)->put(route('expense-categories.update', $category), ['name' => 'Rent', 'status' => 'archived-forever'])
        ->assertSessionHasErrors('status');

    Sanctum::actingAs($this->owner, ['*']);
    $this->putJson(route('api.expense-categories.update', $category), ['status' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors('status');

    expect($category->fresh()->status->value)->toBe('active');
});

it('gives a shop created through the API without a code one of its own', function () {
    Sanctum::actingAs($this->owner, ['*']);

    $code = $this->postJson('/api/shops', ['name' => 'Kilimani Store'])->assertCreated()->json('data.code');

    expect($code)->toStartWith('SHOP-')
        ->and(Shop::where('name', 'Kilimani Store')->value('country'))->toBe('Kenya');
});
