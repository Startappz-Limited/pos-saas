<?php

use App\Enums\ExpenseCategoryType;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\User;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);
    $this->actingAs($this->user);

    $this->category = ExpenseCategory::create([
        'name' => 'Office Supplies',
        'type' => ExpenseCategoryType::SUPPLIES,
        'created_by' => $this->user->id,
    ]);
});

describe('web', function () {
    it('displays expenses on cash register show page', function () {
        $register = CashRegister::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'expense_opening_balance' => 500,
            'expense_balance_used' => 150,
            'status' => 'open',
            'register_date' => today(),
        ]);

        Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'cash_register_id' => $register->id,
            'settled_from_register' => true,
            'title' => 'Office Supplies Purchase',
            'amount' => 75.50,
            'expense_date' => today(),
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $this->get(route('cash-registers.show', $register))
            ->assertSuccessful()
            ->assertSee('Expenses Settled from Register')
            ->assertSee('Office Supplies Purchase')
            ->assertSee($this->category->name);
    });

    it('displays expense balance info on index when active register has expense balance', function () {
        CashRegister::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'expense_opening_balance' => 300,
            'expense_balance_used' => 100,
            'status' => 'open',
            'register_date' => today(),
        ]);

        $this->get(route('cash-registers.index'))
            ->assertSuccessful()
            ->assertSee('Expense Opening Balance')
            ->assertSee('Expense Balance Used')
            ->assertSee('Expense Balance Remaining');
    });

    it('hides expense balance section when no expense opening balance', function () {
        CashRegister::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'expense_opening_balance' => 0,
            'status' => 'open',
            'register_date' => today(),
        ]);

        $this->get(route('cash-registers.index'))
            ->assertSuccessful()
            ->assertDontSee('Expense Opening Balance');
    });

    it('shows expense balance details on show page', function () {
        $register = CashRegister::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'expense_opening_balance' => 500,
            'expense_balance_used' => 200,
            'status' => 'open',
            'register_date' => today(),
        ]);

        $this->get(route('cash-registers.show', $register))
            ->assertSuccessful()
            ->assertSee('Expense Opening Balance')
            ->assertSee('Expense Balance Used')
            ->assertSee('Expense Balance Remaining');
    });

    it('displays all payment method categories in sales summary', function () {
        $register = CashRegister::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'total_sales' => 1000,
            'total_cash_sales' => 300,
            'total_card_sales' => 200,
            'total_credit_sales' => 150,
            'total_mobile_money_sales' => 175,
            'total_bank_transfer_sales' => 100,
            'total_cheque_sales' => 75,
            'status' => 'open',
            'register_date' => today(),
        ]);

        $this->get(route('cash-registers.show', $register))
            ->assertSuccessful()
            ->assertSee('Cash:')
            ->assertSee('Card:')
            ->assertSee('Credit:')
            ->assertSee('Mobile Money:')
            ->assertSee('Bank Transfer:')
            ->assertSee('Cheque:');
    });

    it('displays expense payment method in expenses table', function () {
        $register = CashRegister::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'expense_opening_balance' => 500,
            'status' => 'open',
            'register_date' => today(),
        ]);

        Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'cash_register_id' => $register->id,
            'settled_from_register' => true,
            'title' => 'Cash Expense',
            'amount' => 50.00,
            'payment_method' => 'cash',
            'expense_date' => today(),
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'cash_register_id' => $register->id,
            'settled_from_register' => true,
            'title' => 'Mobile Expense',
            'amount' => 30.00,
            'payment_method' => 'mobile_money',
            'expense_date' => today(),
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $this->get(route('cash-registers.show', $register))
            ->assertSuccessful()
            ->assertSee('Payment Method')
            ->assertSee('Cash')
            ->assertSee('Mobile Money');
    });
});

describe('api', function () {
    it('includes expense data in show response', function () {
        $register = CashRegister::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'expense_opening_balance' => 500,
            'expense_balance_used' => 150,
            'status' => 'open',
            'register_date' => today(),
        ]);

        Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'cash_register_id' => $register->id,
            'settled_from_register' => true,
            'title' => 'Test Expense',
            'amount' => 75.00,
            'payment_method' => 'cash',
            'expense_date' => today(),
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.cash-registers.show', $register))
            ->assertSuccessful()
            ->assertJsonPath('data.expenses.0.title', 'Test Expense')
            ->assertJsonPath('data.expenses.0.settled_from_register', true)
            ->assertJsonPath('data.expenses.0.category', $this->category->name)
            ->assertJsonPath('data.expenses.0.payment_method', 'cash');

        $data = $response->json('data');
        expect((float) $data['expense_opening_balance'])->toBe(500.0)
            ->and((float) $data['expense_balance_used'])->toBe(150.0)
            ->and((float) $data['remaining_expense_balance'])->toBe(350.0)
            ->and((float) $data['expenses'][0]['amount'])->toBe(75.0);
    });

    it('includes remaining expense balance in status response', function () {
        CashRegister::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'expense_opening_balance' => 400,
            'expense_balance_used' => 100,
            'status' => 'open',
            'register_date' => today(),
        ]);

        $response = $this->getJson(route('api.cash-registers.status'))
            ->assertSuccessful()
            ->assertJsonPath('data.has_active_register', true);

        $register = $response->json('data.register');
        expect((float) $register['expense_opening_balance'])->toBe(400.0)
            ->and((float) $register['expense_balance_used'])->toBe(100.0)
            ->and((float) $register['remaining_expense_balance'])->toBe(300.0);
    });

    it('returns expenses total in show response', function () {
        $register = CashRegister::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'expense_opening_balance' => 500,
            'status' => 'open',
            'register_date' => today(),
        ]);

        Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'cash_register_id' => $register->id,
            'settled_from_register' => true,
            'title' => 'Expense A',
            'amount' => 50.00,
            'expense_date' => today(),
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        Expense::create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'cash_register_id' => $register->id,
            'settled_from_register' => true,
            'title' => 'Expense B',
            'amount' => 30.00,
            'expense_date' => today(),
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson(route('api.cash-registers.show', $register))
            ->assertSuccessful();

        expect((float) $response->json('data.expenses_total'))->toBe(80.0);
    });
});
