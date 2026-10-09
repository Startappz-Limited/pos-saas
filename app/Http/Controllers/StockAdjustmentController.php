<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(protected StockAdjustmentService $stockAdjustmentService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockAdjustment::class);

        $adjustments = $this->stockAdjustmentService->getAllAdjustments(
            perPage: $request->input('per_page', 15),
            search: $request->input('search'),
            status: $request->input('status'),
            type: $request->input('type'),
            shopId: $request->input('shop_id'),
        );

        $statistics = $this->stockAdjustmentService->getStatistics();
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();

        return view('stock-adjustments.index', compact('adjustments', 'statistics', 'shops'));
    }

    public function create(): View
    {
        $this->authorize('create', StockAdjustment::class);

        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();
        $products = Product::active()->select('id', 'name')->orderBy('name')->get();
        $adjustmentTypes = AdjustmentType::cases();
        $adjustmentReasons = AdjustmentReason::cases();

        return view('stock-adjustments.create', compact('shops', 'products', 'adjustmentTypes', 'adjustmentReasons'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', StockAdjustment::class);

        $validated = $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'type' => 'required|string|in:increase,decrease',
            'reason' => 'required|string|in:'.implode(',', array_column(AdjustmentReason::cases(), 'value')),
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_change' => 'required|integer|min:1',
            'items.*.item_notes' => 'nullable|string|max:500',
        ]);

        $adjustment = $this->stockAdjustmentService->createAdjustment(
            $validated,
            $request->user()->id
        );

        return redirect()
            ->route('stock-adjustments.show', $adjustment)
            ->with('success', 'Stock adjustment created successfully.');
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        $this->authorize('view', $stockAdjustment);

        $stockAdjustment->load(['shop', 'creator', 'approver', 'items.product']);

        return view('stock-adjustments.show', compact('stockAdjustment'));
    }

    public function edit(StockAdjustment $stockAdjustment): View|RedirectResponse
    {
        $this->authorize('update', $stockAdjustment);

        if (! $stockAdjustment->isPending()) {
            return redirect()
                ->route('stock-adjustments.show', $stockAdjustment)
                ->with('error', 'Only pending adjustments can be edited.');
        }

        $stockAdjustment->load(['shop', 'items.product']);
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();
        $products = Product::active()->select('id', 'name')->orderBy('name')->get();
        $adjustmentTypes = AdjustmentType::cases();
        $adjustmentReasons = AdjustmentReason::cases();

        return view('stock-adjustments.edit', compact('stockAdjustment', 'shops', 'products', 'adjustmentTypes', 'adjustmentReasons'));
    }

    public function update(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('update', $stockAdjustment);

        $validated = $request->validate([
            'type' => 'required|string|in:increase,decrease',
            'reason' => 'required|string|in:'.implode(',', array_column(AdjustmentReason::cases(), 'value')),
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_change' => 'required|integer|min:1',
            'items.*.item_notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->stockAdjustmentService->updateAdjustment($stockAdjustment, $validated);

            return redirect()
                ->route('stock-adjustments.show', $stockAdjustment)
                ->with('success', 'Stock adjustment updated successfully.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('delete', $stockAdjustment);

        try {
            $this->stockAdjustmentService->deleteAdjustment($stockAdjustment);

            return redirect()
                ->route('stock-adjustments.index')
                ->with('success', 'Stock adjustment deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('approve', $stockAdjustment);

        try {
            $this->stockAdjustmentService->approveAdjustment(
                $stockAdjustment,
                $request->user()->id
            );

            return back()->with('success', 'Stock adjustment approved successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('reject', $stockAdjustment);

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        try {
            $this->stockAdjustmentService->rejectAdjustment(
                $stockAdjustment,
                $request->user()->id,
                $validated['rejection_reason'] ?? null
            );

            return back()->with('success', 'Stock adjustment rejected.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function complete(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->authorize('complete', $stockAdjustment);

        try {
            $this->stockAdjustmentService->completeAdjustment($stockAdjustment);

            return back()->with('success', 'Stock adjustment completed. Inventory has been updated.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
