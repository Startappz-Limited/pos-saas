<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * The recorded low-stock route (stage 11c, step 1): the same list and shape as GET products/low-stock,
     * from LowStockQuery.
     */
    public function lowStock(Request $request, ProductService $products): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $products->getLowStockProducts(
                perPage: $request->input('per_page', 15),
                shopId: $request->integer('shop_id') ?: null,
            ),
        ]);
    }
}
