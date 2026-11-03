<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentReason;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Services\Stock\StockLedger;
use App\Services\StockAdjustmentService;
use App\Support\Stock\AdjustmentApproval;
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
        $products = $this->adjustableProducts();
        $adjustmentReasons = AdjustmentReason::offered();

        return view('stock-adjustments.create', compact('shops', 'products', 'adjustmentReasons'));
    }

    public function store(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        try {
            $adjustment = $this->stockAdjustmentService->createAdjustment($request->validated(), $request->user()->id);
        } catch (\Exception $e) {
            return $this->failed($e);
        }

        // Applied at once unless it needs a second person (stage 10, step 1g).
        return redirect()
            ->route('stock-adjustments.show', $adjustment)
            ->with('success', $adjustment->isCompleted()
                ? __('Stock adjustment :number applied. Stock has been updated.', ['number' => $adjustment->adjustment_number])
                : __('Stock adjustment :number saved. It waits for someone else to approve it.', ['number' => $adjustment->adjustment_number]));
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        $this->authorize('view', $stockAdjustment);

        $stockAdjustment->load(['shop', 'creator', 'approver', 'items.product', 'items.variation']);
        $approval = app(AdjustmentApproval::class);
        $ledger = app(StockLedger::class);

        return view('stock-adjustments.show', compact('stockAdjustment', 'approval', 'ledger'));
    }

    public function edit(StockAdjustment $stockAdjustment): View|RedirectResponse
    {
        $this->authorize('update', $stockAdjustment);

        if (! $stockAdjustment->isPending()) {
            return redirect()
                ->route('stock-adjustments.show', $stockAdjustment)
                ->with('error', 'Only pending adjustments can be edited.');
        }

        $stockAdjustment->load(['shop' => fn ($query) => $query->withTrashed(), 'items.product', 'items.variation']);
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();
        $products = $this->adjustableProducts();
        $adjustmentReasons = AdjustmentReason::offered();

        return view('stock-adjustments.edit', compact('stockAdjustment', 'shops', 'products', 'adjustmentReasons'));
    }

    public function update(StoreStockAdjustmentRequest $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        try {
            $this->stockAdjustmentService->updateAdjustment($stockAdjustment, $request->validated());

            return redirect()
                ->route('stock-adjustments.show', $stockAdjustment)
                ->with('success', 'Stock adjustment updated successfully.');
        } catch (\Exception $e) {
            return $this->failed($e);
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
            return $this->failed($e);
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

            return back()->with('success', __('Stock adjustment approved and applied. Stock has been updated.'));
        } catch (\Exception $e) {
            return $this->failed($e);
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
            return $this->failed($e);
        }
    }

    /**
     * Active products with their named variations, for the line picker (stage 10,
     * step 1g): ids and names only, since the page reaches clerks, who see no costs.
     *
     * @return array<int, array{id: int, name: string, variations: array<int, array{id: int, name: string}>}>
     */
    private function adjustableProducts(): array
    {
        return Product::active()
            ->with(['variations' => fn ($query) => $query->where('is_default', false)->select('id', 'product_id', 'name')->orderBy('name')])
            ->select('id', 'name')->orderBy('name')->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'variations' => $product->variations->map(fn ($variation): array => ['id' => $variation->id, 'name' => (string) $variation->name])->values()->all(),
            ])->all();
    }
}
