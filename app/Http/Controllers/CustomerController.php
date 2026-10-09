<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerPurchaseHistoryRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shop;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomerController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of customers.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Customer type filter
        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        $customers = $query->latest()->paginate(15)->withQueryString();

        $stats = Customer::query()->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN customer_type = 'retail' THEN 1 ELSE 0 END) as retail,
            SUM(CASE WHEN customer_type = 'wholesale' THEN 1 ELSE 0 END) as wholesale
        ")->first();

        $statistics = [
            'total' => (int) $stats->total,
            'active' => (int) $stats->active,
            'retail' => (int) $stats->retail,
            'wholesale' => (int) $stats->wholesale,
            'total_spent' => 0,
            'total_orders' => 0,
        ];

        return view('customers.index', compact('customers', 'statistics'));
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create(): View
    {
        $this->authorize('create', Customer::class);

        $shops = Shop::select('id', 'name')->where('status', 'active')->orderBy('name')->get();

        return view('customers.create', compact('shops'));
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $validated = $request->validated();

        // Generate unique customer code
        $validated['code'] = $this->generateCustomerCode();
        $validated['uuid'] = (string) Str::uuid();
        $validated['shop_id'] = auth()->user()->shop_id ?? Shop::first()?->id ?? 1;
        $validated['created_by'] = auth()->id();

        // Set credit balance to 0 initially
        $validated['credit_balance'] = 0;

        // If credit is not allowed, set credit limit to 0
        if (! ($validated['allow_credit'] ?? false)) {
            $validated['credit_limit'] = 0;
        }

        $customer = Customer::create($validated);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer created successfully.');
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->loadCount('sales');
        $customer->total_spent = (float) $customer->sales()->where('status', 'completed')->sum('total_amount');
        $purchases = $customer->sales()
            ->with(['shop', 'source'])
            ->latest()
            ->limit(10)
            ->get();

        return view('customers.show', compact('customer', 'purchases'));
    }

    public function purchases(CustomerPurchaseHistoryRequest $request, Customer $customer): View
    {
        $this->authorize('view', $customer);

        $report = $this->buildPurchaseHistoryReport($request, $customer, true);

        return view('customers.purchases', $report);
    }

    public function purchasesPdf(CustomerPurchaseHistoryRequest $request, Customer $customer): HttpResponse
    {
        $this->authorize('view', $customer);

        $report = $this->buildPurchaseHistoryReport($request, $customer, false);
        $pdf = Pdf::loadView('pdf.customer-purchase-history', $report);
        $customerCode = $customer->code ?: 'customer';
        $period = Str::slug($report['periodLabel']);

        return $pdf->download("customer-purchases-{$customerCode}-{$period}.pdf");
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        $shops = Shop::select('id', 'name')->where('status', 'active')->orderBy('name')->get();

        return view('customers.edit', compact('customer', 'shops'));
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validated();
        $validated['updated_by'] = auth()->id();

        // If credit is not allowed, set credit limit to 0
        if (! ($validated['allow_credit'] ?? $customer->allow_credit)) {
            $validated['credit_limit'] = 0;
        }

        $customer->update($validated);

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    /**
     * Activate the specified customer.
     */
    public function activate(Customer $customer): RedirectResponse
    {
        $this->authorize('activate', $customer);

        $customer->update([
            'status' => 'active',
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Customer activated successfully.');
    }

    /**
     * Deactivate the specified customer.
     */
    public function deactivate(Customer $customer): RedirectResponse
    {
        $this->authorize('deactivate', $customer);

        $customer->update([
            'status' => 'inactive',
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', 'Customer deactivated successfully.');
    }

    /**
     * Display customers with credit alerts (near or over credit limit).
     */
    public function creditAlerts(): View
    {
        $this->authorize('viewAny', Customer::class);

        $customers = Customer::where('allow_credit', true)
            ->whereRaw('credit_balance >= credit_limit * 0.8')
            ->orderByRaw('credit_balance / NULLIF(credit_limit, 0) DESC')
            ->paginate(15);

        return view('customers.credit-alerts', compact('customers'));
    }

    /**
     * Generate a unique customer code.
     */
    private function generateCustomerCode(): string
    {
        do {
            $code = 'CUS-'.strtoupper(Str::random(6));
        } while (Customer::where('code', $code)->exists());

        return $code;
    }

    private function buildPurchaseHistoryReport(CustomerPurchaseHistoryRequest $request, Customer $customer, bool $paginate): array
    {
        $validated = $request->validated();
        $customer->load('shop');
        $dateFrom = $request->date('date_from')?->startOfDay();
        $dateTo = $request->date('date_to')?->endOfDay();
        $query = $this->customerPurchasesQuery($customer, $validated, $dateFrom, $dateTo);
        $filters = [
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'status' => $validated['status'] ?? null,
            'payment_status' => $validated['payment_status'] ?? null,
            'search' => $validated['search'] ?? null,
        ];
        $purchases = $paginate
            ? (clone $query)->latest('created_at')->latest('id')->paginate(25)->withQueryString()
            : (clone $query)->latest('created_at')->latest('id')->get();

        return [
            'customer' => $customer,
            'purchases' => $purchases,
            'filters' => $filters,
            'periodLabel' => $this->purchasePeriodLabel($filters),
            'statusLabel' => $this->purchaseStatusLabel($filters['status']),
            'paymentStatusLabel' => $this->paymentStatusLabel($filters['payment_status']),
            'statusOptions' => $this->purchaseStatusOptions(),
            'paymentStatusOptions' => $this->paymentStatusOptions(),
            'summary' => [
                'count' => (clone $query)->count(),
                'completed_count' => (clone $query)->where('status', 'completed')->count(),
                'total_amount' => (float) (clone $query)->sum('total_amount'),
                'paid_amount' => (float) (clone $query)->sum('paid_amount'),
                'balance_due' => (float) (clone $query)->sum('balance_due'),
            ],
        ];
    }

    private function customerPurchasesQuery(Customer $customer, array $validated, ?Carbon $dateFrom, ?Carbon $dateTo): Builder
    {
        return Sale::query()
            ->with(['shop', 'source'])
            ->whereBelongsTo($customer)
            ->when($dateFrom, fn (Builder $query): Builder => $query->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn (Builder $query): Builder => $query->where('created_at', '<=', $dateTo))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($validated['payment_status'] ?? null, fn (Builder $query, string $paymentStatus): Builder => $query->where('payment_status', $paymentStatus))
            ->when($validated['search'] ?? null, fn (Builder $query, string $search): Builder => $query->where('invoice_number', 'like', "%{$search}%"));
    }

    /**
     * @return array<string, string>
     */
    private function purchaseStatusOptions(): array
    {
        return [
            'completed' => 'Completed',
            'pending' => 'Pending',
            'voided' => 'Voided',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function paymentStatusOptions(): array
    {
        return [
            'paid' => 'Paid',
            'partial' => 'Partial',
            'unpaid' => 'Unpaid',
        ];
    }

    private function purchaseStatusLabel(?string $status): string
    {
        return $status ? $this->purchaseStatusOptions()[$status] : 'All Sale Statuses';
    }

    private function paymentStatusLabel(?string $paymentStatus): string
    {
        return $paymentStatus ? $this->paymentStatusOptions()[$paymentStatus] : 'All Payment Statuses';
    }

    private function purchasePeriodLabel(array $filters): string
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
