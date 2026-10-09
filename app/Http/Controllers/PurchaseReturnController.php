<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseReturnReason;
use App\Http\Requests\StorePurchaseReturnRequest;
use App\Http\Requests\UpdatePurchaseReturnRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\ReturnItem;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\StockIntake;
use App\Models\Supplier;
use App\Services\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function __construct(private PurchaseReturnService $purchaseReturnService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseReturn::class);

        $purchaseReturns = $this->purchaseReturnService->getAllReturns(
            perPage: $request->integer('per_page', 15),
            search: $request->input('search'),
            status: $request->input('status'),
            reason: $request->input('reason'),
            supplierId: $request->integer('supplier_id') ?: null,
            shopId: $request->integer('shop_id') ?: null,
        );

        $statistics = $this->purchaseReturnService->getStatistics();
        $suppliers = Supplier::active()->select('id', 'name')->orderBy('name')->get();
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();

        return view('purchase-returns.index', compact('purchaseReturns', 'statistics', 'suppliers', 'shops'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PurchaseReturn::class);

        $purchaseOrder = $this->findPurchaseOrder($request->input('purchase_order_id'));
        $stockIntake = $this->findStockIntake($request->input('stock_intake_id'));
        $customerReturn = $this->findCustomerReturn($request->input('sale_return_id') ?? $request->input('return_id'));
        $returnItem = $this->findReturnItem($request->input('return_item_id'));

        if ($returnItem && ! $customerReturn) {
            $customerReturn = $returnItem->saleReturn;
        }

        if ($stockIntake && ! $purchaseOrder) {
            $purchaseOrder = $stockIntake->purchaseOrder;
        }

        $suppliers = Supplier::active()->select('id', 'name')->orderBy('name')->get();
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();
        $stockIntakes = $this->availableStockIntakes();
        $returnItems = $this->availableCustomerReturnItems();
        $reasons = PurchaseReturnReason::cases();
        $sourceOptions = $this->buildSourceOptions($stockIntakes, $returnItems);
        $initialItems = $this->buildInitialItems($stockIntake, $returnItem);
        $defaults = [
            'supplier_id' => $stockIntake?->supplier_id ?? $returnItem?->product?->supplier_id ?? $purchaseOrder?->supplier_id,
            'shop_id' => $stockIntake?->shop_id ?? $returnItem?->saleReturn?->shop_id ?? $purchaseOrder?->shop_id,
            'purchase_order_id' => $purchaseOrder?->id,
            'sale_return_id' => $customerReturn?->id,
        ];

        return view('purchase-returns.create', compact(
            'purchaseOrder',
            'stockIntake',
            'customerReturn',
            'returnItem',
            'suppliers',
            'shops',
            'stockIntakes',
            'returnItems',
            'reasons',
            'sourceOptions',
            'initialItems',
            'defaults',
        ));
    }

    public function store(StorePurchaseReturnRequest $request): RedirectResponse
    {
        $purchaseReturn = $this->purchaseReturnService->createReturn(
            $request->validated(),
            $request->user()->id,
        );

        return redirect()
            ->route('purchase-returns.show', $purchaseReturn)
            ->with('success', 'Supplier return created successfully.');
    }

    public function show(PurchaseReturn $purchaseReturn): View
    {
        $this->authorize('view', $purchaseReturn);

        $purchaseReturn->load([
            'purchaseOrder',
            'supplier',
            'shop',
            'saleReturn.customer',
            'items.product',
            'items.productVariation',
            'items.purchaseOrderItem',
            'items.stockIntake',
            'items.returnItem.saleReturn',
            'requestedBy',
            'approvedBy',
            'shippedBy',
            'completedBy',
            'cancelledBy',
        ]);

        return view('purchase-returns.show', compact('purchaseReturn'));
    }

    public function edit(PurchaseReturn $purchaseReturn): View|RedirectResponse
    {
        $this->authorize('update', $purchaseReturn);

        if (! $purchaseReturn->canEdit()) {
            return redirect()
                ->route('purchase-returns.show', $purchaseReturn)
                ->with('error', 'Only draft or pending supplier returns can be edited.');
        }

        $purchaseReturn->load(['items.product', 'items.stockIntake', 'items.returnItem.saleReturn', 'purchaseOrder', 'saleReturn']);

        $suppliers = Supplier::active()->select('id', 'name')->orderBy('name')->get();
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();
        $stockIntakes = $this->availableStockIntakes($purchaseReturn);
        $returnItems = $this->availableCustomerReturnItems($purchaseReturn);
        $reasons = PurchaseReturnReason::cases();
        $sourceOptions = $this->buildSourceOptions($stockIntakes, $returnItems);
        $initialItems = $this->buildInitialItemsFromPurchaseReturn($purchaseReturn);
        $defaults = [
            'supplier_id' => $purchaseReturn->supplier_id,
            'shop_id' => $purchaseReturn->shop_id,
            'purchase_order_id' => $purchaseReturn->purchase_order_id,
            'sale_return_id' => $purchaseReturn->sale_return_id,
        ];

        return view('purchase-returns.edit', compact('purchaseReturn', 'suppliers', 'shops', 'stockIntakes', 'returnItems', 'reasons', 'sourceOptions', 'initialItems', 'defaults'));
    }

    public function update(UpdatePurchaseReturnRequest $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        try {
            $this->purchaseReturnService->updateReturn($purchaseReturn, $request->validated(), $request->user()->id);

            return redirect()
                ->route('purchase-returns.show', $purchaseReturn)
                ->with('success', 'Supplier return updated successfully.');
        } catch (\Exception $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function destroy(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('delete', $purchaseReturn);

        try {
            $this->purchaseReturnService->deleteReturn($purchaseReturn);

            return redirect()
                ->route('purchase-returns.index')
                ->with('success', 'Supplier return deleted successfully.');
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function approve(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('approve', $purchaseReturn);

        try {
            $this->purchaseReturnService->approveReturn($purchaseReturn, $request->user()->id);

            return back()->with('success', 'Supplier return approved successfully.');
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function ship(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('ship', $purchaseReturn);

        $validated = $request->validate([
            'shipment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->purchaseReturnService->markAsShipped(
                $purchaseReturn,
                $request->user()->id,
                $validated['shipment_reference'] ?? null,
            );

            return back()->with('success', 'Supplier return marked as returned to supplier and inventory was reduced.');
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function approveAndShip(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('approveAndShip', $purchaseReturn);

        $validated = $request->validate([
            'shipment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->purchaseReturnService->approveAndShipReturn(
                $purchaseReturn,
                $request->user()->id,
                $validated['shipment_reference'] ?? null,
            );

            return back()->with('success', 'Supplier return approved and marked as returned to supplier.');
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function complete(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('complete', $purchaseReturn);

        $validated = $request->validate([
            'supplier_credit_amount' => ['nullable', 'numeric', 'min:0'],
            'supplier_credit_reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->purchaseReturnService->completeReturn($purchaseReturn, $validated, $request->user()->id);

            return back()->with('success', 'Supplier return completed successfully.');
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    public function cancel(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->authorize('cancel', $purchaseReturn);

        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->purchaseReturnService->cancelReturn(
                $purchaseReturn,
                $request->user()->id,
                $validated['cancellation_reason'] ?? null,
            );

            return back()->with('success', 'Supplier return cancelled.');
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }

    private function findPurchaseOrder(mixed $value): ?PurchaseOrder
    {
        if (! $value) {
            return null;
        }

        return PurchaseOrder::query()
            ->with(['supplier', 'shop'])
            ->when(is_numeric($value), fn ($query) => $query->orWhere('id', $value))
            ->orWhere('uuid', $value)
            ->firstOrFail();
    }

    private function findStockIntake(mixed $value): ?StockIntake
    {
        if (! $value) {
            return null;
        }

        return StockIntake::query()
            ->with(['purchaseOrder', 'purchaseOrderItem', 'supplier', 'shop', 'product', 'productVariation'])
            ->when(is_numeric($value), fn ($query) => $query->orWhere('id', $value))
            ->orWhere('uuid', $value)
            ->firstOrFail();
    }

    private function findCustomerReturn(mixed $value): ?SaleReturn
    {
        if (! $value) {
            return null;
        }

        return SaleReturn::query()
            ->with(['customer', 'shop', 'items.product', 'items.saleItem'])
            ->when(is_numeric($value), fn ($query) => $query->orWhere('id', $value))
            ->orWhere('uuid', $value)
            ->firstOrFail();
    }

    private function findReturnItem(mixed $value): ?ReturnItem
    {
        if (! $value) {
            return null;
        }

        return ReturnItem::query()
            ->with(['saleReturn.customer', 'saleReturn.shop', 'product.supplier', 'saleItem'])
            ->when(is_numeric($value), fn ($query) => $query->orWhere('id', $value))
            ->orWhere('uuid', $value)
            ->firstOrFail();
    }

    private function availableStockIntakes(?PurchaseReturn $purchaseReturn = null)
    {
        $selectedIds = $purchaseReturn?->items->pluck('stock_intake_id')->filter() ?? collect();

        return StockIntake::completed()
            ->with(['purchaseOrder', 'purchaseOrderItem', 'supplier:id,name', 'shop:id,name', 'product:id,name,sku,cost_price', 'productVariation:id,name,sku,cost_price,stock_quantity'])
            ->where('quantity_accepted', '>', 0)
            ->when($selectedIds->isNotEmpty(), fn ($query) => $query->orWhereIn('id', $selectedIds))
            ->latest('completed_at')
            ->limit(100)
            ->get();
    }

    private function availableCustomerReturnItems(?PurchaseReturn $purchaseReturn = null)
    {
        $selectedIds = $purchaseReturn?->items->pluck('return_item_id')->filter() ?? collect();

        return ReturnItem::query()
            ->with(['saleReturn.customer', 'saleReturn.shop', 'product.supplier:id,name', 'saleItem'])
            ->where('return_to_supplier', true)
            ->when($selectedIds->isNotEmpty(), fn ($query) => $query->orWhereIn('id', $selectedIds))
            ->latest()
            ->limit(100)
            ->get();
    }

    private function buildSourceOptions($stockIntakes, $returnItems): array
    {
        return $stockIntakes
            ->map(fn (StockIntake $stockIntake): array => $this->buildStockIntakeSourceOption($stockIntake))
            ->merge($returnItems->map(fn (ReturnItem $returnItem): array => $this->buildReturnItemSourceOption($returnItem)))
            ->values()
            ->all();
    }

    private function buildStockIntakeSourceOption(StockIntake $stockIntake): array
    {
        return [
            'key' => 'stock_intake:'.$stockIntake->id,
            'source_type' => 'stock_intake',
            'label' => $stockIntake->intake_number.' - '.$stockIntake->product?->name.' - '.$stockIntake->supplier?->name.' - '.$stockIntake->shop?->name,
            'stock_intake_id' => $stockIntake->id,
            'return_item_id' => null,
            'purchase_order_item_id' => $stockIntake->purchase_order_item_id,
            'purchase_order_id' => $stockIntake->purchase_order_id,
            'sale_return_id' => null,
            'supplier_id' => $stockIntake->supplier_id,
            'supplier_name' => $stockIntake->supplier?->name,
            'shop_id' => $stockIntake->shop_id,
            'shop_name' => $stockIntake->shop?->name,
            'product_id' => $stockIntake->product_id,
            'product_variation_id' => $stockIntake->product_variation_id,
            'product_name' => $stockIntake->product?->name ?? 'Unknown product',
            'reference' => $stockIntake->purchaseOrder?->order_number ?? $stockIntake->intake_number,
            'available_quantity' => (int) $stockIntake->quantity_accepted,
            'unit_cost' => (float) ($stockIntake->purchaseOrderItem?->unit_cost ?? $stockIntake->product?->cost_price ?? 0),
            'condition' => $stockIntake->quality_status,
            'notes' => $stockIntake->quality_notes,
        ];
    }

    private function buildReturnItemSourceOption(ReturnItem $returnItem): array
    {
        return [
            'key' => 'return_item:'.$returnItem->id,
            'source_type' => 'return_item',
            'label' => $returnItem->saleReturn?->return_number.' - '.$returnItem->product?->name.' - '.$returnItem->product?->supplier?->name.' - '.$returnItem->saleReturn?->shop?->name,
            'stock_intake_id' => null,
            'return_item_id' => $returnItem->id,
            'purchase_order_item_id' => null,
            'purchase_order_id' => null,
            'sale_return_id' => $returnItem->return_id,
            'supplier_id' => $returnItem->product?->supplier_id,
            'supplier_name' => $returnItem->product?->supplier?->name,
            'shop_id' => $returnItem->saleReturn?->shop_id,
            'shop_name' => $returnItem->saleReturn?->shop?->name,
            'product_id' => $returnItem->product_id,
            'product_variation_id' => $returnItem->saleItem?->variation_id,
            'product_name' => $returnItem->product?->name ?? 'Unknown product',
            'reference' => $returnItem->saleReturn?->return_number,
            'available_quantity' => (int) $returnItem->quantity,
            'unit_cost' => (float) ($returnItem->saleItem?->unit_cost ?? $returnItem->product?->cost_price ?? 0),
            'condition' => $returnItem->condition,
            'notes' => $returnItem->return_to_supplier_notes ?? $returnItem->condition_notes,
        ];
    }

    private function buildInitialItems(?StockIntake $stockIntake, ?ReturnItem $returnItem): array
    {
        if ($stockIntake) {
            $source = $this->buildStockIntakeSourceOption($stockIntake);

            return [[
                ...$source,
                'quantity' => min(1, max(1, $source['available_quantity'])),
            ]];
        }

        if ($returnItem) {
            $source = $this->buildReturnItemSourceOption($returnItem);

            return [[
                ...$source,
                'quantity' => min(1, max(1, $source['available_quantity'])),
            ]];
        }

        return [];
    }

    private function buildInitialItemsFromPurchaseReturn(PurchaseReturn $purchaseReturn): array
    {
        return $purchaseReturn->items->map(function ($item) use ($purchaseReturn): array {
            $sourceKey = $item->stock_intake_id
                ? 'stock_intake:'.$item->stock_intake_id
                : 'return_item:'.$item->return_item_id;

            return [
                'key' => $sourceKey,
                'stock_intake_id' => $item->stock_intake_id,
                'return_item_id' => $item->return_item_id,
                'purchase_order_item_id' => $item->purchase_order_item_id,
                'purchase_order_id' => $item->purchase_order_item_id ? $purchaseReturn->purchase_order_id : null,
                'sale_return_id' => $item->returnItem?->return_id,
                'product_id' => $item->product_id,
                'product_variation_id' => $item->product_variation_id,
                'product_name' => $item->product?->name ?? 'Unknown product',
                'reference' => $item->stockIntake?->intake_number ?? $item->returnItem?->saleReturn?->return_number,
                'available_quantity' => $item->quantity,
                'quantity' => $item->quantity,
                'unit_cost' => (float) $item->unit_cost,
                'condition' => $item->condition,
                'notes' => $item->notes,
            ];
        })->values()->all();
    }
}
