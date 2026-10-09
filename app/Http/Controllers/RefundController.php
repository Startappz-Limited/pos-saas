<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessRefundRequest;
use App\Models\Refund;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Services\ReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function __construct(protected ReturnService $returnService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Refund::class);

        $query = Refund::query()
            ->with(['saleReturn', 'customer', 'shop', 'processedBy']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('refund_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->input('shop_id'));
        }

        $refunds = $query->latest()->paginate($request->input('per_page', 15));
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();

        return view('refunds.index', compact('refunds', 'shops'));
    }

    public function show(Refund $refund): View
    {
        $this->authorize('view', $refund);

        $refund->load([
            'saleReturn.sale',
            'saleReturn.items.product',
            'customer',
            'shop',
            'processedBy',
        ]);

        return view('refunds.show', compact('refund'));
    }

    public function store(ProcessRefundRequest $request): RedirectResponse
    {
        $this->authorize('create', Refund::class);

        $saleReturn = SaleReturn::findOrFail($request->validated('return_id'));

        try {
            $refund = $this->returnService->createRefund(
                $saleReturn,
                $request->validated(),
                $request->user()->id
            );

            return redirect()
                ->route('refunds.show', $refund)
                ->with('success', 'Refund created successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function process(Request $request, Refund $refund): RedirectResponse
    {
        $this->authorize('process', $refund);

        $validated = $request->validate([
            'transaction_id' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->returnService->processRefund(
                $refund,
                $request->user()->id,
                $validated['transaction_id'] ?? null
            );

            return back()->with('success', 'Refund processed successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
