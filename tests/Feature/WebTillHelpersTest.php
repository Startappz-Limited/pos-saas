<?php

use App\Models\CashRegister;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
 * The web's till helpers need sales.create, as the flat API's do (hotfix
 * fix/api-authz, 2 November 2026): opening and closing a register, and adding a
 * sale source or delivery company from the sale screen. Before, any signed-in
 * member could, on the web.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->shop = Shop::factory()->create();
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('accountant');
    $this->accountant->shops()->attach($this->shop);
    $this->cashier = User::factory()->create();
    $this->cashier->assignRole('cashier');
    $this->cashier->shops()->attach($this->shop);
});

it('refuses opening and closing a register, and the quick adds, to a member without sales.create', function () {
    $session = CashRegister::factory()->create(['shop_id' => $this->shop->id, 'user_id' => $this->cashier->id]);
    $this->actingAs($this->accountant);

    $this->post(route('cash-registers.store'), ['opening_balance' => 100])->assertForbidden();
    $this->post(route('cash-registers.close', $session), ['closing_balance' => 100])->assertForbidden();
    $this->postJson(route('sales.storeSaleSource'), ['name' => 'Roadside stall'])->assertForbidden();
    $this->postJson(route('sales.storeDeliveryCompany'), ['name' => 'Boda Express', 'phone' => '0712345678'])->assertForbidden();

    expect($session->fresh()->status)->toBe('open')
        ->and(DB::table('sale_sources')->where('name', 'Roadside stall')->exists())->toBeFalse()
        ->and(DB::table('delivery_companies')->where('name', 'Boda Express')->exists())->toBeFalse();
});

it('lets a cashier open a register and use the quick adds on the web', function () {
    $this->actingAs($this->cashier);

    $this->post(route('cash-registers.store'), ['opening_balance' => 100])->assertRedirect(route('cash-registers.index'));
    $this->postJson(route('sales.storeSaleSource'), ['name' => 'Roadside stall'])->assertSuccessful();
    $this->postJson(route('sales.storeDeliveryCompany'), ['name' => 'Boda Express', 'phone' => '0712345678'])->assertSuccessful();

    expect(CashRegister::query()->where('shop_id', $this->shop->id)->where('status', 'open')->exists())->toBeTrue();
});
