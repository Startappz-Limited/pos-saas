<?php

use App\Enums\ExpenseCategoryType;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\User;

/**
 * `User::shop_id` is an accessor over the `shop_user` pivot, so it returns null
 * for any user with no shop attached — and a large share of real accounts have
 * none. Every sibling API controller falls back to `Shop::first()?->id`;
 * Api\ExpenseController did not, which meant those users saw an empty expense
 * list, got a 500 on create (expenses.shop_id is NOT NULL), and a 403 on show.
 *
 * These tests act as a user with NO shop assigned — the broken case. Since
 * staff must now be linked to a shop to work at all, that user is a shop owner
 * (admin): they reach every shop of their business without a pivot row.
 */
beforeEach(function () {
    $this->shop = Shop::factory()->create();

    // Deliberately NOT attached to any shop.
    $this->user = User::factory()->owner()->create();
    expect($this->user->assignedShopIds())->toBeEmpty();

    $this->actingAs($this->user);

    $this->category = ExpenseCategory::create([
        'name' => 'Office Supplies',
        'type' => ExpenseCategoryType::SUPPLIES,
        'created_by' => $this->user->id,
    ]);
});

function makeExpense(int $shopId, int $userId, int $categoryId, string $title): Expense
{
    return Expense::create([
        'shop_id' => $shopId,
        'category_id' => $categoryId,
        'title' => $title,
        'amount' => 1200,
        'expense_date' => today()->toDateString(),
        'status' => ExpenseStatus::DRAFT,
        'created_by' => $userId,
    ]);
}

it('lists expenses for a user with no shop assigned', function () {
    makeExpense($this->shop->id, $this->user->id, $this->category->id, 'Printer paper');

    $response = $this->getJson('/api/expenses')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.title'))->toBe('Printer paper');
});

it('creates an expense for a user with no shop assigned', function () {
    // Previously wrote shop_id = null into a NOT NULL column → 500.
    $this->postJson('/api/expenses', [
        'category_id' => $this->category->id,
        'title' => 'Cleaning supplies',
        'amount' => 450,
        'expense_date' => today()->toDateString(),
    ])->assertCreated();

    $this->assertDatabaseHas('expenses', [
        'title' => 'Cleaning supplies',
        'shop_id' => $this->shop->id,
    ]);
});

it('allows showing an expense for a user with no shop assigned', function () {
    $expense = makeExpense($this->shop->id, $this->user->id, $this->category->id, 'Fuel');

    // authorizeShopAccess() compared against null, so this always aborted 403.
    $this->getJson("/api/expenses/{$expense->uuid}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Fuel');
});

it('returns a summary for a user with no shop assigned', function () {
    makeExpense($this->shop->id, $this->user->id, $this->category->id, 'Rent');

    $this->getJson('/api/expenses/summary')->assertOk();
});

it('still scopes a shop-assigned user to their own shop', function () {
    // The fallback must not become a cross-shop leak.
    $otherShop = Shop::factory()->create();
    $scopedUser = User::factory()->create();
    $scopedUser->shops()->attach($otherShop);

    makeExpense($this->shop->id, $this->user->id, $this->category->id, 'Not yours');

    $this->actingAs($scopedUser)
        ->getJson('/api/expenses')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
