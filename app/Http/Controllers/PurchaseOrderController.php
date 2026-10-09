<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $purchaseOrderService)
    {
        //
    }

    public function index(Request $request): View
    {
        // Every other action on this controller authorizes; index did not, so the
        // list was readable by any authenticated user regardless of permission.
        $this->authorize('viewAny', PurchaseOrder::class);

        $purchaseOrders = $this->purchaseOrderService->getAllPurchaseOrders(
            perPage: $request->input('per_page', 15),
            search: $request->input('search'),
            status: $request->input('status'),
            supplierId: $request->input('supplier_id'),
            shopId: $request->input('shop_id'),
        );

        $statistics = $this->purchaseOrderService->getStatistics();

        return view('purchase-orders.index', compact('purchaseOrders', 'statistics'));
    }

    public function create(): View
    {
        $this->authorize('create', PurchaseOrder::class);

        $suppliers = Supplier::active()->select('id', 'name')->orderBy('name')->get();
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();
        $products = Product::active()
            ->select('id', 'name', 'sku', 'has_variations', 'selling_price', 'cost_price')
            ->with(['shops:id', 'variations:id,product_id,name,sku,selling_price,cost_price'])
            ->orderBy('name')
            ->get();

        return view('purchase-orders.create', compact('suppliers', 'shops', 'products'));
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $data = $request->validated();
        $workflow = $data['workflow'] ?? null;
        unset($data['workflow']);

        if ($workflow === 'approve_and_mark_ordered') {
            $data['status'] = PurchaseOrderStatus::PENDING;
        }

        $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder(
            $data,
            $request->user()->id
        );

        if ($workflow === 'approve_and_mark_ordered') {
            $purchaseOrder = $this->purchaseOrderService->approveAndMarkAsOrdered(
                $purchaseOrder,
                $request->user()->id
            );
        }

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order created successfully.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load(['items.product', 'items.productVariation', 'supplier', 'shop', 'stockIntakes.purchaseReturnItems.purchaseReturn', 'purchaseReturns', 'creator', 'approver']);

        return view('purchase-orders.show', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder->load(['items.product', 'items.productVariation']);
        $suppliers = Supplier::active()->select('id', 'name')->orderBy('name')->get();
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();
        $products = Product::active()
            ->select('id', 'name', 'sku', 'has_variations', 'selling_price', 'cost_price')
            ->with(['shops:id', 'variations:id,product_id,name,sku,selling_price,cost_price'])
            ->orderBy('name')
            ->get();

        return view('purchase-orders.edit', compact('purchaseOrder', 'suppliers', 'shops', 'products'));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        $this->purchaseOrderService->updatePurchaseOrder(
            $purchaseOrder,
            $request->validated(),
            $request->user()->id
        );

        return redirect()
            ->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order updated successfully.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);

        $this->purchaseOrderService->deletePurchaseOrder($purchaseOrder);

        return redirect()
            ->route('purchase-orders.index')
            ->with('success', 'Purchase order deleted successfully.');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('approve', $purchaseOrder);

        $this->purchaseOrderService->approvePurchaseOrder(
            $purchaseOrder,
            $request->user()->id
        );

        return back()->with('success', 'Purchase order approved successfully.');
    }

    public function approveAndMarkAsOrdered(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('approveAndMarkAsOrdered', $purchaseOrder);

        $this->purchaseOrderService->approveAndMarkAsOrdered(
            $purchaseOrder,
            $request->user()->id
        );

        return back()->with('success', 'Purchase order approved and marked as ordered.');
    }

    public function submitForApproval(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('submitForApproval', $purchaseOrder);

        $this->purchaseOrderService->submitForApproval(
            $purchaseOrder,
            $request->user()->id
        );

        return back()->with('success', 'Purchase order submitted for approval.');
    }

    public function submitApproveAndMarkAsOrdered(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('submitApproveAndMarkAsOrdered', $purchaseOrder);

        $this->purchaseOrderService->submitApproveAndMarkAsOrdered(
            $purchaseOrder,
            $request->user()->id
        );

        return back()->with('success', 'Purchase order submitted, approved, and marked as ordered.');
    }

    public function markAsOrdered(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('markAsOrdered', $purchaseOrder);

        $this->purchaseOrderService->markAsOrdered(
            $purchaseOrder,
            $request->user()->id
        );

        return back()->with('success', 'Purchase order marked as ordered.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $this->purchaseOrderService->cancelPurchaseOrder(
            $purchaseOrder,
            $request->user()->id
        );

        return back()->with('success', 'Purchase order cancelled successfully.');
    }

    public function pending(): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $purchaseOrders = $this->purchaseOrderService->getPendingPurchaseOrders();

        return view('purchase-orders.pending', compact('purchaseOrders'));
    }

    public function overdue(): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $purchaseOrders = $this->purchaseOrderService->getOverduePurchaseOrders();

        return view('purchase-orders.overdue', compact('purchaseOrders'));
    }
}
