<?php

namespace App\Http\Controllers;

use App\Actions\GenerateCreditStatementPdf;
use App\Enums\CreditAccountStatus;
use App\Enums\CreditTransactionType;
use App\Http\Requests\CreditAccountStatementRequest;
use App\Http\Requests\StoreCreditAccountRequest;
use App\Http\Requests\SuspendCreditAccountRequest;
use App\Http\Requests\UpdateCreditAccountRequest;
use App\Http\Requests\UpdateCreditLimitRequest;
use App\Jobs\SendCreditStatementViaBaileysJob;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Shop;
use App\Services\CreditAccountService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CreditAccountController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected CreditAccountService $creditAccountService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CreditAccount::class);

        $requestedShopId = $request->integer('shop_id') ?: null;

        if ($requestedShopId !== null) {
            abort_unless($request->user()->canAccessShop($requestedShopId), 403);
        }

        // visible_to: this listing was previously unscoped, so a user assigned to
        // one shop could read every shop's customer balances.
        $accounts = $this->creditAccountService->getAccounts([
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'shop_id' => $requestedShopId,
            'visible_to' => $request->user(),
            'has_balance' => $request->boolean('has_balance'),
        ]);

        $statistics = $this->creditAccountService->getStatistics(
            $requestedShopId ?? $request->user()->shop_id
        );
        $shops = Shop::active()->orderBy('name')->get(['id', 'name']);
        $statuses = CreditAccountStatus::cases();

        return view('credit-accounts.index', compact('accounts', 'statistics', 'shops', 'statuses'));
    }

    public function create(): View
    {
        $this->authorize('create', CreditAccount::class);

        $customers = Customer::with('shop')
            ->whereDoesntHave('creditAccounts')
            ->where('customer_type', 'wholesale')
            ->where('allow_credit', true)
            ->orderBy('name')
            ->get();
        $shops = Shop::active()->orderBy('name')->get(['id', 'name']);
        $statuses = CreditAccountStatus::cases();

        return view('credit-accounts.create', compact('customers', 'shops', 'statuses'));
    }

    public function store(StoreCreditAccountRequest $request): RedirectResponse
    {
        $this->authorize('create', CreditAccount::class);

        $validated = $request->validated();
        $customer = Customer::findOrFail($validated['customer_id']);
        $shop = Shop::findOrFail($validated['shop_id']);

        $creditAccount = $this->creditAccountService->createAccount($customer, $shop, $validated);

        return redirect()
            ->route('credit-accounts.show', $creditAccount)
            ->with('success', 'Credit account created successfully.');
    }

    public function show(CreditAccount $creditAccount): View
    {
        $this->authorize('view', $creditAccount);

        $creditAccount->load(['customer', 'shop', 'creator', 'limitUpdater']);
        $recentTransactions = $creditAccount->transactions()->latest()->limit(10)->get();
        $overdueAmount = $creditAccount->overdueAmount();

        return view('credit-accounts.show', compact('creditAccount', 'recentTransactions', 'overdueAmount'));
    }

    public function edit(CreditAccount $creditAccount): View
    {
        $this->authorize('update', $creditAccount);

        $creditAccount->load(['customer', 'shop']);
        $statuses = CreditAccountStatus::cases();

        return view('credit-accounts.edit', compact('creditAccount', 'statuses'));
    }

    public function update(UpdateCreditAccountRequest $request, CreditAccount $creditAccount): RedirectResponse
    {
        $this->authorize('update', $creditAccount);

        $creditAccount = $this->creditAccountService->updateAccount($creditAccount, $request->validated());

        return redirect()
            ->route('credit-accounts.show', $creditAccount)
            ->with('success', 'Credit account updated successfully.');
    }

    public function destroy(CreditAccount $creditAccount): RedirectResponse
    {
        $this->authorize('delete', $creditAccount);

        $this->creditAccountService->deleteAccount($creditAccount);

        return redirect()
            ->route('credit-accounts.index')
            ->with('success', 'Credit account deleted successfully.');
    }

    public function transactions(CreditAccount $creditAccount): View
    {
        $this->authorize('view', $creditAccount);

        $creditAccount->load(['customer', 'shop']);
        $transactions = $creditAccount->transactions()->latest()->paginate(20);

        return view('credit-accounts.transactions', compact('creditAccount', 'transactions'));
    }

    public function statement(CreditAccountStatementRequest $request, CreditAccount $creditAccount): View
    {
        $this->authorize('view', $creditAccount);

        $statement = $this->buildStatement($request, $creditAccount);
        $types = CreditTransactionType::cases();

        return view('credit-accounts.statement', [...$statement, 'types' => $types]);
    }

    public function statementPdf(CreditAccountStatementRequest $request, CreditAccount $creditAccount): HttpResponse
    {
        $this->authorize('view', $creditAccount);

        $statement = $this->buildStatement($request, $creditAccount);
        $pdf = Pdf::loadView('pdf.credit-account-statement', $statement);
        $customerCode = $creditAccount->customer?->code ?: 'customer';
        $period = Str::slug($statement['periodLabel']);

        return $pdf->download("credit-statement-{$customerCode}-{$period}.pdf");
    }

    /**
     * The outstanding-debt statement, re-rendered live.
     *
     * Reached from the signed link attached to the customer's WhatsApp
     * statement, so it carries no session — the signature is the authorization.
     */
    public function outstandingStatementPdf(CreditAccount $creditAccount, GenerateCreditStatementPdf $statementPdf): HttpResponse
    {
        return response($statementPdf->execute($creditAccount), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$statementPdf->filename($creditAccount).'"',
        ]);
    }

    /**
     * WhatsApp the customer their statement of outstanding invoices.
     */
    public function sendStatement(Request $request, CreditAccount $creditAccount): RedirectResponse
    {
        $this->authorize('sendStatement', $creditAccount);
        abort_unless($request->user()->canAccessShop($creditAccount->shop_id), 403);

        $creditAccount->loadMissing('customer');

        if (empty($creditAccount->customer?->phone)) {
            return back()->with('error', __('This customer has no phone number on file.'));
        }

        SendCreditStatementViaBaileysJob::dispatch($creditAccount, $request->user()->id);

        return back()->with('success', __('Statement queued for delivery to :name on WhatsApp.', [
            'name' => $creditAccount->customer->name,
        ]));
    }

    public function suspend(SuspendCreditAccountRequest $request, CreditAccount $creditAccount): RedirectResponse
    {
        $this->authorize('suspend', $creditAccount);

        $this->creditAccountService->suspendAccount($creditAccount, $request->validated('reason'));

        return back()->with('success', 'Credit account suspended successfully.');
    }

    public function reactivate(CreditAccount $creditAccount): RedirectResponse
    {
        $this->authorize('reactivate', $creditAccount);

        $this->creditAccountService->reactivateAccount($creditAccount);

        return back()->with('success', 'Credit account reactivated successfully.');
    }

    public function adjustLimit(UpdateCreditLimitRequest $request, CreditAccount $creditAccount): RedirectResponse
    {
        $this->authorize('updateLimit', $creditAccount);

        $this->creditAccountService->updateCreditLimit($creditAccount, (float) $request->validated('credit_limit'));

        return back()->with('success', 'Credit limit updated successfully.');
    }

    public function overdue(Request $request): View
    {
        $this->authorize('viewOverdue', CreditAccount::class);

        $shopId = $request->integer('shop_id') ?: null;
        $overdueAccounts = $this->creditAccountService->getOverdueAccounts($shopId);
        $aging = $this->creditAccountService->getAgingReport($shopId);
        $shops = Shop::active()->orderBy('name')->get(['id', 'name']);

        return view('credit-accounts.overdue', compact('overdueAccounts', 'aging', 'shops'));
    }

    public function agingReport(Request $request): View
    {
        $this->authorize('viewAgingReport', CreditAccount::class);

        $shopId = $request->integer('shop_id') ?: null;
        $aging = $this->creditAccountService->getAgingReport($shopId);
        $shops = Shop::active()->orderBy('name')->get(['id', 'name']);

        return view('credit-accounts.aging-report', compact('aging', 'shops'));
    }

    private function buildStatement(CreditAccountStatementRequest $request, CreditAccount $creditAccount): array
    {
        $validated = $request->validated();
        $creditAccount->load(['customer', 'shop']);
        $dateFrom = $request->date('date_from')?->startOfDay();
        $dateTo = $request->date('date_to')?->endOfDay();

        $transactionsQuery = $creditAccount->transactions()
            ->when($dateFrom, fn ($query) => $query->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->where('created_at', '<=', $dateTo))
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->orderBy('created_at')
            ->orderBy('id');

        $transactions = $transactionsQuery->get();
        $openingBalance = $this->openingStatementBalance($creditAccount, $dateFrom, $transactions->first());
        $closingBalance = $transactions->isNotEmpty() ? (float) $transactions->last()->balance_after : $openingBalance;
        $filters = [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'type' => $validated['type'] ?? null,
        ];

        return [
            'creditAccount' => $creditAccount,
            'transactions' => $transactions,
            'filters' => $filters,
            'periodLabel' => $this->statementPeriodLabel($filters),
            'typeLabel' => $filters['type'] ? CreditTransactionType::from($filters['type'])->label() : 'All Transactions',
            'openingBalance' => $openingBalance,
            'closingBalance' => $closingBalance,
            'totalDebits' => (float) $transactions->sum('debit'),
            'totalCredits' => (float) $transactions->sum('credit'),
        ];
    }

    private function openingStatementBalance(CreditAccount $creditAccount, mixed $dateFrom, mixed $firstTransaction): float
    {
        if ($firstTransaction) {
            return (float) $firstTransaction->balance_before;
        }

        if (! $dateFrom) {
            return 0.0;
        }

        return (float) ($creditAccount->transactions()
            ->where('created_at', '<', $dateFrom)
            ->latest('created_at')
            ->latest('id')
            ->value('balance_after') ?? 0);
    }

    private function statementPeriodLabel(array $filters): string
    {
        if ($filters['date_from'] && $filters['date_to']) {
            return $filters['date_from'].' to '.$filters['date_to'];
        }

        if ($filters['date_from']) {
            return 'From '.$filters['date_from'];
        }

        if ($filters['date_to']) {
            return 'Until '.$filters['date_to'];
        }

        return 'All Time';
    }
}
