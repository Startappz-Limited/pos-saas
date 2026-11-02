<?php

use App\Actions\CheckLowStockLevels;
use App\Exceptions\BusinessRuleException;
use App\Models\CashRegister;
use App\Models\ExpenseCategory;
use App\Models\LowStockAlert;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
 * Hard-coded tenant fallbacks (hotfix fix/api-authz, 2 November 2026). A write
 * that needs a shop refuses a member who has none (`ViewerShop::require()`),
 * where it used to store a null shop (a 500) or shop 0; a low-stock check with
 * no user records no creator instead of user 1, who may belong to another
 * business.
 */

beforeEach(function () {
    seedPermissions();
    $this->member = User::factory()->create();
    $this->member->givePermissionTo(['sales.create', 'expenses.view', 'expenses.create']);
    $this->message = "You haven't been given a shop yet. Ask the owner.";
});

it('refuses an expense from a member with no shop on the web, without a 500', function () {
    $category = ExpenseCategory::factory()->create();

    $this->actingAs($this->member)->from(route('expenses.index'))->post(route('expenses.store'), [
        'category_id' => $category->id,
        'title' => 'Printer paper',
        'amount' => 450,
        'expense_date' => today()->toDateString(),
        'payment_method' => 'cash',
    ])->assertRedirect(route('expenses.index'))->assertSessionHasErrors(['shop' => $this->message]);

    expect(DB::table('expenses')->where('title', 'Printer paper')->exists())->toBeFalse();
});

it('refuses to open a register for a member with no shop, on the web and the API', function () {
    $this->actingAs($this->member)->from(route('cash-registers.open'))
        ->post(route('cash-registers.store'), ['opening_balance' => 100])
        ->assertRedirect(route('cash-registers.open'))->assertSessionHasErrors(['shop' => $this->message]);

    Sanctum::actingAs($this->member, ['*']);
    $this->postJson('/api/cash-registers/open', ['opening_balance' => 100])
        ->assertUnprocessable()->assertJsonPath('errors.shop.0', $this->message);

    expect(CashRegister::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('opens a register on the web without opening notes', function () {
    // opening_notes is optional, but the controller read it unconditionally (a 500).
    $shop = Shop::factory()->create();
    $this->member->shops()->attach($shop);

    $this->actingAs($this->member)->post(route('cash-registers.store'), ['opening_balance' => 100])
        ->assertRedirect(route('cash-registers.index'))->assertSessionHasNoErrors();

    expect(CashRegister::query()->where('shop_id', $shop->id)->where('status', 'open')->exists())->toBeTrue();
});

it('never opens a register session without a shop', function () {
    $this->actingAs($this->member);

    expect(fn () => CashRegister::openRegister())->toThrow(BusinessRuleException::class, $this->message);
});

it('records no creator for a low-stock alert raised without a user, never user 1', function () {
    $shop = Shop::factory()->create();
    $product = Product::factory()->create(['shop_id' => $shop->id, 'reorder_level' => 5]);
    DB::table('shop_variation')->where('variation_id', $product->defaultVariation->id)->where('shop_id', $shop->id)
        ->update(['stock_quantity' => 1]);
    $this->actingAs(tap(User::factory()->create(), fn (User $owner) => $owner->assignRole(ownerRole())));

    app(CheckLowStockLevels::class)->execute($shop->id);

    $alert = LowStockAlert::query()->where('product_id', $product->id)->firstOrFail();
    expect($alert->created_by)->toBeNull();
});
