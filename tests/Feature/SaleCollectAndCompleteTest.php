<?php

use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
 * Collecting a payment and completing a sale (hotfix fix/api-authz, decided
 * 2 November 2026):
 * - collecting is allowed with sales.update, credit-sales.collect or
 *   payments.create, so an accountant collects;
 * - completing a pending sale needs sales.create and has no 24-hour limit (a
 *   delivery or held sale is completed when handed over); editing a sale keeps
 *   the 24-hour limit.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->shop = Shop::factory()->create();
    $this->member = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->shops()->attach($this->shop);

        return $user;
    };
});

it('lets an accountant collect a payment on a sale, through the API', function () {
    Sanctum::actingAs(($this->member)('accountant'), ['*']);
    $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'payment_status' => 'unpaid', 'total_amount' => 500, 'balance_due' => 500]);

    $this->postJson("/api/sales/{$sale->getRouteKey()}/collect-payment", [
        'payments' => [['amount' => 200, 'payment_method' => 'cash']],
    ])->assertOk();
});

it('refuses collecting to a member with none of sales.update, credit-sales.collect or payments.create', function () {
    $member = User::factory()->create();
    $member->givePermissionTo(['sales.view', 'sales.create']);
    $member->shops()->attach($this->shop);
    Sanctum::actingAs($member, ['*']);
    $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'payment_status' => 'unpaid']);

    $this->postJson("/api/sales/{$sale->getRouteKey()}/collect-payment", [
        'payments' => [['amount' => 200, 'payment_method' => 'cash']],
    ])->assertForbidden();
});

it('lets a cashier complete a pending sale made two days ago, on the API and the web', function () {
    $cashier = ($this->member)('cashier');
    $api = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'pending', 'created_at' => now()->subDays(2)]);
    $web = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'pending', 'created_at' => now()->subDays(2)]);

    Sanctum::actingAs($cashier, ['*']);
    $this->postJson("/api/sales/{$api->getRouteKey()}/complete")->assertOk();

    $this->actingAs($cashier)->post(route('sales.complete', $web))->assertRedirect();

    expect($api->fresh()->status)->toBe('completed')
        ->and($web->fresh()->status)->toBe('completed');
});

it('keeps the 24-hour limit on editing: a cashier can\'t edit a two-day-old sale', function () {
    Sanctum::actingAs(($this->member)('cashier'), ['*']);
    $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'pending', 'created_at' => now()->subDays(2), 'notes' => 'Before']);

    $this->putJson("/api/sales/{$sale->getRouteKey()}", ['notes' => 'After'])->assertForbidden();

    expect($sale->fresh()->notes)->toBe('Before');
});

it('refuses completing a sale that isn\'t pending, or by a member without sales.create', function () {
    Sanctum::actingAs(($this->member)('cashier'), ['*']);
    $completed = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'completed']);
    $this->postJson("/api/sales/{$completed->getRouteKey()}/complete")->assertForbidden();

    Sanctum::actingAs(($this->member)('accountant'), ['*']);
    $pending = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'pending']);
    $this->postJson("/api/sales/{$pending->getRouteKey()}/complete")->assertForbidden();

    expect($pending->fresh()->status)->toBe('pending');
});
