<?php

use App\Models\CashRegister;
use App\Models\Shop;
use App\Models\User;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);
    $this->actingAs($this->user);
});

it('can open register with expense opening balance via web', function () {
    $this->post(route('cash-registers.store'), [
        'opening_balance' => 500,
        'expense_opening_balance' => 200,
        'opening_notes' => 'Test open',
    ])->assertRedirect();

    $this->assertDatabaseHas('cash_registers', [
        'shop_id' => $this->shop->id,
        'opening_balance' => 500,
        'expense_opening_balance' => 200,
    ]);
});

it('defaults expense opening balance to zero when not provided via web', function () {
    $this->post(route('cash-registers.store'), [
        'opening_balance' => 500,
        'opening_notes' => null,
    ])->assertRedirect();

    $this->assertDatabaseHas('cash_registers', [
        'shop_id' => $this->shop->id,
        'opening_balance' => 500,
        'expense_opening_balance' => 0,
    ]);
});

it('can open register with expense opening balance via API', function () {
    $this->postJson('/api/cash-registers/open', [
        'opening_balance' => 1000,
        'expense_opening_balance' => 350,
        'opening_notes' => 'API open',
    ])->assertCreated();

    $this->assertDatabaseHas('cash_registers', [
        'shop_id' => $this->shop->id,
        'opening_balance' => 1000,
        'expense_opening_balance' => 350,
    ]);
});

it('defaults expense opening balance to zero when not provided via API', function () {
    $this->postJson('/api/cash-registers/open', [
        'opening_balance' => 1000,
    ])->assertCreated();

    $this->assertDatabaseHas('cash_registers', [
        'shop_id' => $this->shop->id,
        'opening_balance' => 1000,
        'expense_opening_balance' => 0,
    ]);
});

it('validates expense opening balance is numeric via API', function () {
    $this->postJson('/api/cash-registers/open', [
        'opening_balance' => 500,
        'expense_opening_balance' => 'abc',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('expense_opening_balance');
});

it('validates expense opening balance is non-negative via API', function () {
    $this->postJson('/api/cash-registers/open', [
        'opening_balance' => 500,
        'expense_opening_balance' => -100,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('expense_opening_balance');
});

it('includes expense opening balance in status response', function () {
    CashRegister::factory()->create([
        'shop_id' => $this->shop->id,
        'user_id' => $this->user->id,
        'opening_balance' => 500,
        'expense_opening_balance' => 200,
        'status' => 'open',
        'register_date' => today(),
    ]);

    $response = $this->getJson('/api/cash-registers/status')
        ->assertSuccessful()
        ->assertJsonPath('data.has_active_register', true);

    expect((float) $response->json('data.register.expense_opening_balance'))->toBe(200.0);
});
