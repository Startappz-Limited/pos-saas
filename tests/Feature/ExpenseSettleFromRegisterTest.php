<?php

use App\Enums\ExpenseCategoryType;
use App\Enums\ExpenseStatus;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);

    Permission::create(['name' => 'expenses.create']);
    Permission::create(['name' => 'expenses.view']);
    Permission::create(['name' => 'expenses.update']);
    $this->user->givePermissionTo(['expenses.create', 'expenses.view', 'expenses.update']);
    $this->actingAs($this->user);

    $this->category = ExpenseCategory::create([
        'name' => 'Office Supplies',
        'type' => ExpenseCategoryType::SUPPLIES,
        'created_by' => $this->user->id,
    ]);

    $this->register = CashRegister::factory()->create([
        'shop_id' => $this->shop->id,
        'user_id' => $this->user->id,
        'expense_opening_balance' => 5000,
        'expense_balance_used' => 0,
        'status' => 'open',
        'register_date' => today(),
    ]);
});

it('settles expense from register balance via web', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'title' => 'Office Supplies',
        'amount' => 1500,
        'expense_date' => today()->toDateString(),
        'settle_from_register' => 1,
    ])->assertRedirect();

    $this->assertDatabaseHas('expenses', [
        'title' => 'Office Supplies',
        'amount' => 1500,
        'settled_from_register' => true,
        'cash_register_id' => $this->register->id,
        'is_paid' => true,
        'payment_method' => 'cash',
        'status' => 'paid',
    ]);

    $this->register->refresh();
    expect((float) $this->register->expense_balance_used)->toBe(1500.0);
});

it('creates normal expense when settle_from_register is not checked via web', function () {
    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'title' => 'Regular Expense',
        'amount' => 500,
        'expense_date' => today()->toDateString(),
    ])->assertRedirect();

    $this->assertDatabaseHas('expenses', [
        'title' => 'Regular Expense',
        'settled_from_register' => false,
        'cash_register_id' => null,
        'status' => 'draft',
    ]);

    $this->register->refresh();
    expect((float) $this->register->expense_balance_used)->toBe(0.0);
});

it('settles expense from register balance via API', function () {
    $this->postJson('/api/expenses', [
        'category_id' => $this->category->id,
        'title' => 'Printer Ink',
        'amount' => 2000,
        'expense_date' => today()->toDateString(),
        'settle_from_register' => true,
    ])->assertCreated()
        ->assertJsonPath('data.settled_from_register', true)
        ->assertJsonPath('data.status', 'paid');

    $this->register->refresh();
    expect((float) $this->register->expense_balance_used)->toBe(2000.0);
});

it('rejects settlement when amount exceeds available balance via API', function () {
    $this->register->update(['expense_balance_used' => 4500]);

    $this->postJson('/api/expenses', [
        'category_id' => $this->category->id,
        'title' => 'Expensive Item',
        'amount' => 1000,
        'expense_date' => today()->toDateString(),
        'settle_from_register' => true,
    ])->assertUnprocessable()
        ->assertJsonPath('success', false);

    $this->assertDatabaseMissing('expenses', ['title' => 'Expensive Item']);
});

it('rejects settlement when no active register exists via API', function () {
    $this->register->update(['status' => 'closed']);

    $this->postJson('/api/expenses', [
        'category_id' => $this->category->id,
        'title' => 'No Register',
        'amount' => 500,
        'expense_date' => today()->toDateString(),
        'settle_from_register' => true,
    ])->assertUnprocessable()
        ->assertJsonPath('success', false);
});

it('rejects settlement when amount exceeds available balance via web', function () {
    $this->register->update(['expense_balance_used' => 4800]);

    $this->post(route('expenses.store'), [
        'category_id' => $this->category->id,
        'title' => 'Over Budget',
        'amount' => 500,
        'expense_date' => today()->toDateString(),
        'settle_from_register' => 1,
    ])->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('expenses', ['title' => 'Over Budget']);
});

it('accumulates expense balance used across multiple settlements', function () {
    $this->postJson('/api/expenses', [
        'category_id' => $this->category->id,
        'title' => 'Expense 1',
        'amount' => 1000,
        'expense_date' => today()->toDateString(),
        'settle_from_register' => true,
    ])->assertCreated();

    $this->postJson('/api/expenses', [
        'category_id' => $this->category->id,
        'title' => 'Expense 2',
        'amount' => 2000,
        'expense_date' => today()->toDateString(),
        'settle_from_register' => true,
    ])->assertCreated();

    $this->register->refresh();
    expect((float) $this->register->expense_balance_used)->toBe(3000.0);
    expect($this->register->getRemainingExpenseBalance())->toBe(2000.0);
});

it('returns remaining expense balance on register model', function () {
    $this->register->update([
        'expense_opening_balance' => 5000,
        'expense_balance_used' => 1500,
    ]);

    expect($this->register->getRemainingExpenseBalance())->toBe(3500.0);
});

describe('settle existing expense from register', function () {
    it('can settle an approved expense from register via web', function () {
        $expense = Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'title' => 'Approved Expense',
            'amount' => 200.00,
            'expense_date' => today(),
            'status' => ExpenseStatus::APPROVED,
            'created_by' => $this->user->id,
        ]);

        $this->post(route('expenses.settleFromRegister', $expense))
            ->assertRedirect();

        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::PAID)
            ->and($expense->settled_from_register)->toBeTrue()
            ->and($expense->cash_register_id)->toBe($this->register->id)
            ->and($expense->is_paid)->toBeTrue()
            ->and($expense->payment_method)->toBe('cash');

        $this->register->refresh();
        expect((float) $this->register->expense_balance_used)->toBe(200.0);
    });

    it('rejects settling a draft expense from register', function () {
        $expense = Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'title' => 'Draft Expense',
            'amount' => 50.00,
            'expense_date' => today(),
            'status' => ExpenseStatus::DRAFT,
            'created_by' => $this->user->id,
        ]);

        $this->post(route('expenses.settleFromRegister', $expense))
            ->assertRedirect()
            ->assertSessionHas('error');

        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::DRAFT);
    });

    it('rejects settling when amount exceeds remaining balance', function () {
        $this->register->update(['expense_balance_used' => 4900]);

        $expense = Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'title' => 'Too Expensive',
            'amount' => 200.00,
            'expense_date' => today(),
            'status' => ExpenseStatus::APPROVED,
            'created_by' => $this->user->id,
        ]);

        $this->post(route('expenses.settleFromRegister', $expense))
            ->assertRedirect()
            ->assertSessionHas('error');

        $expense->refresh();
        expect($expense->status)->toBe(ExpenseStatus::APPROVED);
    });

    it('can settle an approved expense from register via API', function () {
        $expense = Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'title' => 'API Settle Expense',
            'amount' => 150.00,
            'expense_date' => today(),
            'status' => ExpenseStatus::APPROVED,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('api.expenses.settle-from-register', $expense))
            ->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.settled_from_register', true);

        $this->register->refresh();
        expect((float) $this->register->expense_balance_used)->toBe(150.0);
    });

    it('returns error when no active register via API', function () {
        $this->register->update(['status' => 'closed']);

        $expense = Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'title' => 'No Register',
            'amount' => 50.00,
            'expense_date' => today(),
            'status' => ExpenseStatus::APPROVED,
            'created_by' => $this->user->id,
        ]);

        $this->postJson(route('api.expenses.settle-from-register', $expense))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    });

    it('shows settle from register button for approved expenses', function () {
        $expense = Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'title' => 'Approved Test',
            'amount' => 50.00,
            'expense_date' => today(),
            'status' => ExpenseStatus::APPROVED,
            'created_by' => $this->user->id,
        ]);

        $this->get(route('expenses.show', $expense))
            ->assertSuccessful()
            ->assertSee('Settle from Register');
    });

    it('shows register info for settled expenses', function () {
        $expense = Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'title' => 'Settled One',
            'amount' => 50.00,
            'expense_date' => today(),
            'status' => ExpenseStatus::PAID,
            'settled_from_register' => true,
            'cash_register_id' => $this->register->id,
            'is_paid' => true,
            'payment_method' => 'cash',
            'paid_date' => today(),
            'created_by' => $this->user->id,
        ]);

        $this->get(route('expenses.show', $expense))
            ->assertSuccessful()
            ->assertSee('Settled From')
            ->assertSee($this->register->register_number);
    });
});
