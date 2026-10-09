<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function __construct(protected StockMovementService $stockMovementService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockMovement::class);

        $movements = $this->stockMovementService->getAllMovements(
            perPage: $request->input('per_page', 15)
        );

        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();
        $products = Product::active()->select('id', 'name')->orderBy('name')->get();

        return view('stock-movements.index', compact('movements', 'shops', 'products'));
    }

    public function show(StockMovement $stockMovement): View
    {
        $this->authorize('view', $stockMovement);

        $stockMovement->load(['shop', 'product', 'variation', 'creator', 'reference']);

        return view('stock-movements.show', compact('stockMovement'));
    }
}
