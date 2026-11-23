<?php

namespace App\Http\Controllers;

use App\Actions\CreateInventorySnapshot;
use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventorySnapshotController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventorySnapshot::class);

        $query = InventorySnapshot::query()->with(['shop', 'product', 'variation']);

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->input('shop_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('snapshot_date', $request->input('date'));
        }

        $snapshots = $query->latest('snapshot_date')->paginate($request->input('per_page', 15));

        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();
        $products = Product::active()->select('id', 'name')->orderBy('name')->get();

        return view('inventory-snapshots.index', compact('snapshots', 'shops', 'products'));
    }

    public function show(InventorySnapshot $inventorySnapshot): View
    {
        $this->authorize('view', $inventorySnapshot);

        $inventorySnapshot->load(['shop', 'product', 'variation', 'creator']);

        return view('inventory-snapshots.show', compact('inventorySnapshot'));
    }

    /**
     * A manual snapshot of one shop, taken now (stage 11c, step 3): replaces today's manual one for each variation.
     */
    public function generate(Request $request, CreateInventorySnapshot $snapshots): RedirectResponse
    {
        $this->authorize('create', InventorySnapshot::class);

        $validated = $request->validate(['shop_id' => ['required', 'integer']]);
        $shop = $this->accessibleShop($request, (int) $validated['shop_id']);

        $count = $snapshots->execute($shop->id, now()->toDateString(), $request->user()->id, InventorySnapshot::KIND_MANUAL, InventorySnapshot::SOURCE_ON_TIME)->count();

        return redirect()->route('inventory-snapshots.index', ['shop_id' => $shop->id])
            ->with('success', __('Manual snapshot taken for :count variation(s).', ['count' => $count]));
    }

    /**
     * The value of each shop's stock on one day's snapshots (stage 11c, step 3), one per variation (see below). Shows
     * the cost and retail values, the unknown-cost quantity and any shortfall, per shop the viewer may access.
     */
    public function valuationReport(Request $request): View
    {
        $this->authorize('viewAny', InventorySnapshot::class);

        $accessible = app(TenantContext::class)->accessibleShopsQuery($request->user());
        $day = $request->filled('date')
            ? (string) $request->input('date')
            : InventorySnapshot::query()->whereIn('shop_id', $accessible)->max('snapshot_date');

        // One snapshot per variation for the day: the daily or month-end one, else the manual one, so a manual
        // re-take never counts alongside the nightly figure.
        $rows = $day === null ? collect() : InventorySnapshot::query()
            ->with('shop:id,name')
            ->whereIn('shop_id', $accessible)
            ->whereDate('snapshot_date', $day)
            ->get()
            ->groupBy('variation_id')
            ->map(fn ($group) => $group->firstWhere('kind', '!=', InventorySnapshot::KIND_MANUAL) ?? $group->first())
            ->groupBy('shop_id')
            ->map(fn ($snapshots) => [
                'shop' => $snapshots->first()->shop,
                'variations' => $snapshots->count(),
                'total_value' => round((float) $snapshots->sum('total_value'), 2),
                'retail_value' => round((float) $snapshots->sum('retail_value'), 2),
                'unknown_cost_quantity' => $snapshots->reduce(fn (string $sum, InventorySnapshot $s): string => bcadd($sum, (string) $s->unknown_cost_quantity, 3), '0.000'),
                'shortfall_quantity' => $snapshots->reduce(fn (string $sum, InventorySnapshot $s): string => bcadd($sum, (string) $s->shortfall_quantity, 3), '0.000'),
                'rebuilt' => $snapshots->contains(fn (InventorySnapshot $s): bool => $s->source !== InventorySnapshot::SOURCE_ON_TIME),
            ])
            ->values();

        return view('inventory-snapshots.valuation', ['day' => $day, 'rows' => $rows]);
    }

    private function accessibleShop(Request $request, int $shopId): Shop
    {
        return Shop::query()
            ->whereIn('id', app(TenantContext::class)->accessibleShopsQuery($request->user()))
            ->findOrFail($shopId);
    }
}
