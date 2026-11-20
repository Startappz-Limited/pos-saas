<?php

namespace App\Http\Controllers;

use App\Actions\PurchaseOrder\CreatePurchaseOrderAction;
use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Services\Stock\LowStockQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Stock → Low stock (stage 11c, step 1): the one low-stock report, per shop, from LowStockQuery, in base units,
 * largest shortfall first. Selected rows can become a draft purchase order for the shop, for a supplier chosen
 * on the page. The order's quantities are recomputed here from the shortfall, never taken from the form.
 */
class StockLowStockController extends Controller
{
    public function __construct(
        private readonly LowStockQuery $lowStock,
        private readonly CreatePurchaseOrderAction $createOrder,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('stock.view'), 403);

        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();
        $shop = $shops->firstWhere('id', (int) $request->input('shop_id')) ?? $shops->first();

        $rows = collect();
        if ($shop !== null) {
            $rows = $this->named($this->lowStock->forViewer($shop->id))->sortByDesc(fn (array $row): float => (float) $row['shortfall'])->values();
        }

        return view('stock-low-stock.index', [
            'shops' => $shops,
            'shop' => $shop,
            'rows' => $rows,
            'suppliers' => Supplier::active()->select('id', 'name')->orderBy('name')->get(),
            'canOrder' => $request->user()->can('create', PurchaseOrder::class),
        ]);
    }

    public function createPurchaseOrder(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create', PurchaseOrder::class), 403);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['required', 'string', 'regex:/^\d+:\d+$/'],
        ]);

        // Keyed by "product:variation" for the rows that are low at this shop now; a line that has recovered is skipped.
        $low = $this->lowStock->forViewer((int) $validated['shop_id'])
            ->keyBy(fn (array $row): string => $row['product_id'].':'.$row['variation_id']);

        $items = [];
        foreach (array_unique($validated['lines']) as $key) {
            $row = $low->get($key);

            if ($row === null) {
                continue;
            }

            $variation = ProductVariation::query()->withoutGlobalScope(ProductVariation::HIDE_DEFAULT)->find($row['variation_id']);

            $items[] = [
                'product_id' => $row['product_id'],
                'product_variation_id' => $row['is_default'] ? null : $row['variation_id'],
                'quantity_ordered' => $row['shortfall'],
                'unit_cost' => $variation?->cost_price ?? 0,
            ];
        }

        if ($items === []) {
            return back()->withErrors(['lines' => __('None of the chosen products is low at this shop any more.')]);
        }

        $order = $this->createOrder->execute([
            'supplier_id' => (int) $validated['supplier_id'],
            'shop_id' => (int) $validated['shop_id'],
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrderStatus::DRAFT,
            'items' => $items,
        ], $request->user()->id);

        return redirect()->route('purchase-orders.show', $order)->with('success', __('Draft purchase order created from the low stock list. Review it, then submit it for approval.'));
    }

    /**
     * The low rows with their product and variation names, for the page and the order's lines.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function named($rows)
    {
        $products = Product::query()->whereIn('id', $rows->pluck('product_id'))->get(['id', 'name', 'sku'])->keyBy('id');
        $variations = ProductVariation::query()->withoutGlobalScope(ProductVariation::HIDE_DEFAULT)
            ->whereIn('id', $rows->pluck('variation_id'))->get(['id', 'name', 'sku'])->keyBy('id');

        return $rows->map(function (array $row) use ($products, $variations): array {
            $product = $products->get($row['product_id']);
            $variation = $variations->get($row['variation_id']);

            return [
                ...$row,
                'key' => $row['product_id'].':'.$row['variation_id'],
                'product_name' => $product?->name ?? '—',
                'variation_name' => $row['is_default'] ? null : $variation?->name,
                'sku' => $row['is_default'] ? $product?->sku : ($variation?->sku ?: $product?->sku),
            ];
        });
    }
}
