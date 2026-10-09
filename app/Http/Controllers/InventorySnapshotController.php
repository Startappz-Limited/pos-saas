<?php

namespace App\Http\Controllers;

use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\Shop;
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
}
