<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockIntakeRequest;
use App\Http\Requests\UpdateStockIntakeRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\StockIntake;
use App\Models\Supplier;
use App\Services\StockIntakeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockIntakeController extends Controller
{
    public function __construct(protected StockIntakeService $stockIntakeService)
    {
        //
    }

    public function index(Request $request): View
    {
        // The other actions on this controller authorize; index did not, so the list
        // was readable by any authenticated user regardless of permission.
        $this->authorize('viewAny', StockIntake::class);

        $stockIntakes = $this->stockIntakeService->getAllStockIntakes(
            perPage: $request->input('per_page', 15),
            search: $request->input('search'),
            status: $request->input('status'),
            shopId: $request->input('shop_id'),
            supplierId: $request->input('supplier_id'),
        );

        $statistics = $this->stockIntakeService->getStatistics();

        return view('stock-intakes.index', compact('stockIntakes', 'statistics'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', StockIntake::class);

        $purchaseOrderItem = null;
        $purchaseOrder = null;

        if ($request->has('purchase_order_item_id')) {
            $purchaseOrderItem = PurchaseOrderItem::with(['product', 'productVariation', 'purchaseOrder.supplier', 'purchaseOrder.shop'])
                ->where('uuid', $request->input('purchase_order_item_id'))
                ->firstOrFail();
            $purchaseOrder = $purchaseOrderItem->purchaseOrder;
        } elseif ($request->has('purchase_order_id')) {
            $purchaseOrder = PurchaseOrder::with(['items.product', 'items.productVariation', 'supplier', 'shop'])
                ->where('uuid', $request->input('purchase_order_id'))
                ->firstOrFail();
        }

        $pendingOrders = PurchaseOrder::with(['items.product:id,name,sku', 'supplier:id,name'])
            ->whereIn('status', ['ordered', 'partially_received'])
            ->latest()
            ->limit(100)
            ->get();

        // For manual intakes (no PO), load products, suppliers, and shops
        $products = Product::active()
            ->select('id', 'name', 'sku')
            ->orderBy('name')
            ->get()
            ->unique('name');
        $suppliers = Supplier::active()->select('id', 'name')->orderBy('name')->get();
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();

        return view('stock-intakes.create', compact('purchaseOrderItem', 'purchaseOrder', 'pendingOrders', 'products', 'suppliers', 'shops'));
    }

    public function store(StoreStockIntakeRequest $request): RedirectResponse
    {
        $this->authorize('create', StockIntake::class);

        $data = $request->validated();

        if (! empty($data['purchase_order_item_id'])) {
            $purchaseOrderItem = PurchaseOrderItem::findOrFail($data['purchase_order_item_id']);
            $stockIntake = $this->stockIntakeService->createStockIntakeFromPurchaseOrderItem(
                $purchaseOrderItem,
                $data,
                $request->user()->id
            );
        } else {
            $stockIntake = $this->stockIntakeService->createStockIntake(
                $data,
                $request->user()->id
            );
        }

        return redirect()
            ->route('stock-intakes.show', $stockIntake)
            ->with('success', 'Stock intake created successfully.');
    }

    public function show(StockIntake $stockIntake): View
    {
        $this->authorize('view', $stockIntake);

        $stockIntake->load(['product', 'productVariation', 'supplier', 'shop', 'purchaseOrder', 'purchaseOrderItem', 'purchaseReturnItems.purchaseReturn', 'receivedByUser', 'completedByUser', 'creator']);

        return view('stock-intakes.show', compact('stockIntake'));
    }

    public function edit(StockIntake $stockIntake): View
    {
        $this->authorize('update', $stockIntake);

        $stockIntake->load(['product', 'productVariation', 'purchaseOrder.supplier', 'purchaseOrder.shop', 'purchaseOrderItem', 'shop', 'creator']);

        return view('stock-intakes.edit', compact('stockIntake'));
    }

    public function update(UpdateStockIntakeRequest $request, StockIntake $stockIntake): RedirectResponse
    {
        $this->authorize('update', $stockIntake);

        $this->stockIntakeService->updateStockIntake(
            $stockIntake,
            $request->validated(),
            $request->user()->id
        );

        return redirect()
            ->route('stock-intakes.show', $stockIntake)
            ->with('success', 'Stock intake updated successfully.');
    }

    public function destroy(StockIntake $stockIntake): RedirectResponse
    {
        $this->authorize('delete', $stockIntake);

        $stockIntake->delete();

        return redirect()
            ->route('stock-intakes.index')
            ->with('success', 'Stock intake deleted successfully.');
    }

    public function complete(Request $request, StockIntake $stockIntake): RedirectResponse
    {
        $this->authorize('complete', $stockIntake);

        $this->stockIntakeService->completeStockIntake(
            $stockIntake,
            $request->user()->id
        );

        return back()->with('success', 'Stock intake completed successfully. Stock has been added to inventory.');
    }

    public function cancel(Request $request, StockIntake $stockIntake): RedirectResponse
    {
        $this->authorize('cancel', $stockIntake);

        $this->stockIntakeService->cancelStockIntake(
            $stockIntake,
            $request->user()->id
        );

        return back()->with('success', 'Stock intake cancelled successfully.');
    }

    public function pending(): View
    {
        $this->authorize('viewAny', StockIntake::class);

        $stockIntakes = $this->stockIntakeService->getPendingStockIntakes();

        return view('stock-intakes.pending', compact('stockIntakes'));
    }

    public function qualityIssues(): View
    {
        $this->authorize('viewAny', StockIntake::class);

        $stockIntakes = $this->stockIntakeService->getStockIntakesWithQualityIssues();

        return view('stock-intakes.quality-issues', compact('stockIntakes'));
    }
}
