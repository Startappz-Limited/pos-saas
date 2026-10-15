<?php

use App\Enums\ExpenseCategoryType;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\User;

/**
 * `User::shop_id` is an accessor over the `shop_user` pivot, so it is null for
 * a user with no shop attached. Before stage 1a such a user could reach every
 * shop (fail-open), and Api\ExpenseController broke for them (empty list, 500
 * on create, 403 on show). Shop access is now default-deny (decision 4.4): a
 * member with no shop reaches none, and the controller says so cleanly.
 *
 * These tests act as a user with NO shop assigned.
 */
beforeEach(function () {
    $this->shop = Shop::factory()->create();

    // Deliberately NOT attached to any shop.
    $this->user = User::factory()->create();
    expect($this->user->shop_id)->toBeNull();

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

it('lists no expenses for a user with no shop assigned', function () {
    makeExpense($this->shop->id, $this->user->id, $this->category->id, 'Printer paper');

    $response = $this->getJson('/api/expenses')->assertOk();

    expect($response->json('data'))->toBeEmpty();
});

it('refuses, without a 500, to create an expense for a user with no shop assigned', function () {
    // Once wrote shop_id = null into a NOT NULL column → 500.
    $this->postJson('/api/expenses', [
        'category_id' => $this->category->id,
        'title' => 'Cleaning supplies',
        'amount' => 450,
        'expense_date' => today()->toDateString(),
    ])->assertStatus(422);

    $this->assertDatabaseMissing('expenses', ['title' => 'Cleaning supplies']);
});

it('creates an expense in the shop of a user who has one', function () {
    $this->user->shops()->attach($this->shop);

    $this->postJson('/api/expenses', [
        'category_id' => $this->category->id,
        'title' => 'Cleaning supplies',
        'amount' => 450,
        'expense_date' => today()->toDateString(),
    ])->assertCreated();

    $this->assertDatabaseHas('expenses', ['title' => 'Cleaning supplies', 'shop_id' => $this->shop->id]);
});

it('does not show a shop\'s expense to a user with no shop assigned', function () {
    $expense = makeExpense($this->shop->id, $this->user->id, $this->category->id, 'Fuel');

    $this->getJson("/api/expenses/{$expense->uuid}")->assertNotFound();
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
