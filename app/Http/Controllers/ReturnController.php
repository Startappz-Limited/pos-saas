<?php

namespace App\Http\Controllers;

use App\Enums\ReturnReason;
use App\Http\Requests\StoreReturnRequest;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function __construct(protected ReturnService $returnService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SaleReturn::class);

        $returns = $this->returnService->getAllReturns(
            perPage: $request->input('per_page', 15),
            search: $request->input('search'),
            status: $request->input('status'),
            reason: $request->input('reason'),
            shopId: $request->input('shop_id'),
        );

        $statistics = $this->returnService->getStatistics();
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();

        return view('returns.index', compact('returns', 'statistics', 'shops'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SaleReturn::class);

        $sale = null;
        $saleSearch = trim((string) $request->string('sale_search'));
        $selectedSaleId = $request->input('sale_id', $request->old('sale_id'));

        if ($selectedSaleId) {
            $sale = Sale::query()
                ->with(['customer:id,name,phone', 'items.product'])
                ->where('status', 'completed')
                ->findOrFail($selectedSaleId);
        }

        $salesQuery = Sale::query()
            ->select('id', 'invoice_number', 'customer_id', 'walk_in_customer_name', 'walk_in_customer_phone', 'total_amount', 'created_at')
            ->where('status', 'completed')
            ->with('customer:id,name,phone');

        if ($saleSearch !== '') {
            $salesQuery->where(function ($query) use ($saleSearch) {
                $query->where('invoice_number', 'like', "%{$saleSearch}%")
                    ->orWhere('walk_in_customer_name', 'like', "%{$saleSearch}%")
                    ->orWhere('walk_in_customer_phone', 'like', "%{$saleSearch}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($saleSearch) {
                        $customerQuery->where('name', 'like', "%{$saleSearch}%")
                            ->orWhere('phone', 'like', "%{$saleSearch}%");
                    });
            });
        }

        $sales = $salesQuery
            ->latest()
            ->limit($saleSearch !== '' ? 100 : 50)
            ->get();

        if ($sale && ! $sales->contains('id', $sale->id)) {
            $sales->prepend($sale);
        }

        $returnReasons = ReturnReason::cases();

        return view('returns.create', compact('sale', 'sales', 'returnReasons', 'saleSearch'));
    }

    public function store(StoreReturnRequest $request): RedirectResponse
    {
        $this->authorize('create', SaleReturn::class);

        $saleReturn = $this->returnService->createReturn(
            $request->validated(),
            $request->user()->id
        );

        return redirect()
            ->route('returns.show', $saleReturn)
            ->with('success', 'Return request created successfully.');
    }

    public function show(SaleReturn $return): View
    {
        $this->authorize('view', $return);

        $return->load([
            'sale.customer',
            'customer',
            'shop',
            'items.product',
            'items.saleItem',
            'items.purchaseReturnItems.purchaseReturn',
            'requestedBy',
            'approvedBy',
            'inspectedBy',
            'refund.processedBy',
        ]);

        return view('returns.show', ['saleReturn' => $return]);
    }

    public function edit(SaleReturn $return): View|RedirectResponse
    {
        $this->authorize('update', $return);

        if (! $return->isPending()) {
            return redirect()
                ->route('returns.show', $return)
                ->with('error', 'Only pending returns can be edited.');
        }

        $return->load(['sale.items.product', 'items']);
        $returnReasons = ReturnReason::cases();

        return view('returns.edit', ['saleReturn' => $return, 'returnReasons' => $returnReasons]);
    }

    public function update(Request $request, SaleReturn $return): RedirectResponse
    {
        $this->authorize('update', $return);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:'.implode(',', array_column(ReturnReason::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'restocking_fee' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'exists:sale_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.condition' => ['nullable', 'string', 'in:new,opened,damaged,defective'],
            'items.*.condition_notes' => ['nullable', 'string', 'max:500'],
            'items.*.return_to_supplier' => ['nullable', 'boolean'],
            'items.*.return_to_supplier_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->returnService->updateReturn($return, $validated);

            return redirect()
                ->route('returns.show', $return)
                ->with('success', 'Return updated successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(SaleReturn $return): RedirectResponse
    {
        $this->authorize('delete', $return);

        try {
            $this->returnService->deleteReturn($return);

            return redirect()
                ->route('returns.index')
                ->with('success', 'Return deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, SaleReturn $return): RedirectResponse
    {
        $this->authorize('approve', $return);

        try {
            $this->returnService->approveReturn($return, $request->user()->id);

            return back()->with('success', 'Return approved successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, SaleReturn $return): RedirectResponse
    {
        $this->authorize('reject', $return);

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->returnService->rejectReturn(
                $return,
                $request->user()->id,
                $validated['rejection_reason'] ?? null
            );

            return back()->with('success', 'Return rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function receive(Request $request, SaleReturn $return): RedirectResponse
    {
        $this->authorize('receive', $return);

        try {
            $this->returnService->receiveReturn($return);

            return back()->with('success', 'Return items marked as received.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function inspect(Request $request, SaleReturn $return): RedirectResponse
    {
        $this->authorize('inspect', $return);

        $validated = $request->validate([
            'inspection_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->returnService->inspectReturn(
                $return,
                $request->user()->id,
                $validated['inspection_notes'] ?? null
            );

            return back()->with('success', 'Return items inspected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function forSale(Sale $sale): View
    {
        $this->authorize('viewAny', SaleReturn::class);

        $returns = $this->returnService->getReturnsForSale($sale);
        $sale->load('customer');

        return view('returns.for-sale', compact('returns', 'sale'));
    }
}
