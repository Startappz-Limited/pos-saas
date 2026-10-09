<?php

namespace App\Http\Controllers;

use App\Enums\CreditTransactionType;
use App\Http\Requests\StoreCreditTransactionRequest;
use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\Shop;
use App\Services\CreditTransactionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditTransactionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected CreditTransactionService $creditTransactionService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CreditTransaction::class);

        $requestedShopId = $request->integer('shop_id') ?: null;

        if ($requestedShopId !== null) {
            abort_unless($request->user()->canAccessShop($requestedShopId), 403);
        }

        // visible_to: this listing was previously unscoped, so a user assigned to
        // one shop could read every shop's credit movements.
        $transactions = $this->creditTransactionService->getTransactions([
            'credit_account_id' => $request->integer('credit_account_id') ?: null,
            'customer_id' => $request->integer('customer_id') ?: null,
            'shop_id' => $requestedShopId,
            'visible_to' => $request->user(),
            'type' => $request->string('type')->toString(),
        ]);

        $accounts = CreditAccount::with('customer')->visibleTo($request->user())->orderByDesc('created_at')->get();
        $shops = Shop::active()->orderBy('name')->get(['id', 'name']);
        $types = CreditTransactionType::cases();

        return view('credit-transactions.index', compact('transactions', 'accounts', 'shops', 'types'));
    }

    public function store(StoreCreditTransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', CreditTransaction::class);

        $validated = $request->validated();
        $creditAccount = CreditAccount::findOrFail($validated['credit_account_id']);

        $this->creditTransactionService->recordTransaction(
            $creditAccount,
            CreditTransactionType::from($validated['type']),
            (float) $validated['amount'],
            $validated
        );

        return redirect()
            ->route('credit-accounts.show', $creditAccount)
            ->with('success', 'Credit transaction recorded successfully.');
    }

    public function show(CreditTransaction $creditTransaction): View
    {
        $this->authorize('view', $creditTransaction);

        $creditTransaction->load(['creditAccount.customer', 'customer', 'shop', 'creator']);

        return view('credit-transactions.show', compact('creditTransaction'));
    }

    public function overdue(Request $request): View
    {
        $this->authorize('viewAny', CreditTransaction::class);

        $transactions = $this->creditTransactionService->getTransactions([
            'shop_id' => $request->integer('shop_id') ?: null,
            'overdue' => true,
        ]);
        $shops = Shop::active()->orderBy('name')->get(['id', 'name']);

        return view('credit-transactions.overdue', compact('transactions', 'shops'));
    }

    public function byCustomer(Customer $customer): View
    {
        $this->authorize('viewAny', CreditTransaction::class);

        $transactions = $this->creditTransactionService->getTransactions([
            'customer_id' => $customer->id,
        ]);
        $accounts = CreditAccount::with('customer')->where('customer_id', $customer->id)->get();
        $shops = Shop::active()->orderBy('name')->get(['id', 'name']);
        $types = CreditTransactionType::cases();

        return view('credit-transactions.index', compact('transactions', 'accounts', 'shops', 'types', 'customer'));
    }
}
