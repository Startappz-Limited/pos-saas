<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function dailySales(Request $request): JsonResponse
    {
        $date = $request->input('date', today()->toDateString());
        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;

        $statistics = (array) DB::table('sales')
            ->where('shop_id', $shopId)
            ->where('status', 'completed')
            ->whereDate('completed_at', $date)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(total_cost), 0) as total_cost')
            ->selectRaw('COALESCE(SUM(total_profit), 0) as total_profit')
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(CASE WHEN payment_method = \'cash\' THEN total_amount ELSE 0 END), 0) as cash_sales')
            ->selectRaw('COALESCE(SUM(CASE WHEN payment_method = \'card\' THEN total_amount ELSE 0 END), 0) as card_sales')
            ->first();

        $sales = Sale::with(['customer', 'source'])
            ->where('shop_id', $shopId)
            ->where('status', 'completed')
            ->whereDate('completed_at', $date)
            ->latest('completed_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'statistics' => $statistics,
                'sales' => $sales,
            ],
        ]);
    }

    public function dailyRegister(Request $request): JsonResponse
    {
        $date = $request->input('date', today()->toDateString());
        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;

        $register = CashRegister::with(['user', 'closedBy'])
            ->where('shop_id', $shopId)
            ->whereDate('register_date', $date)
            ->first();

        if (! $register) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No register found for this date.',
            ]);
        }

        $register->load('sales.customer');

        return response()->json([
            'success' => true,
            'data' => $register,
        ]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $date = $request->input('date', today()->toDateString());
        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;
        $limit = $request->input('limit', 10);

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.shop_id', $shopId)
            ->where('sales.status', 'completed')
            ->whereDate('sales.completed_at', $date)
            ->selectRaw('products.id, products.name, products.sku')
            ->selectRaw('SUM(sale_items.quantity) as total_quantity')
            ->selectRaw('SUM(sale_items.line_total) as total_revenue')
            ->selectRaw('SUM(sale_items.profit) as total_profit')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topProducts,
        ]);
    }

    public function pendingPayments(Request $request): JsonResponse
    {
        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;

        $sales = Sale::with(['customer', 'source'])
            ->where('shop_id', $shopId)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->latest('created_at')
            ->paginate($request->input('per_page', 20));

        $totals = (array) DB::table('sales')
            ->where('shop_id', $shopId)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->selectRaw('COALESCE(SUM(balance_due), 0) as total_outstanding')
            ->selectRaw('COUNT(*) as total_count')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'sales' => $sales,
                'totals' => $totals,
            ],
        ]);
    }
}
