<?php

namespace App\Http\Controllers;

use App\Enums\EcommerceOrderStatus;
use App\Models\Category;
use App\Models\EcommerceOrder;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $shops = Shop::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedShopId = $request->integer('shop_id') ?: null;

        if ($selectedShopId !== null && ! $shops->contains('id', $selectedShopId)) {
            abort(403);
        }

        // Date ranges
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        // Date-range bindings reused by the sales-stats query. Positional, so
        // the order here must match the order of the CASE expressions below.
        $ranges = [
            $startOfMonth, $endOfMonth,         // current_sales
            $startOfMonth, $endOfMonth,         // current_cost
            $startOfMonth, $endOfMonth,         // current_orders
            $startOfLastMonth, $endOfLastMonth, // last_sales
            $startOfLastMonth, $endOfLastMonth, // last_cost
            $startOfLastMonth, $endOfLastMonth, // last_orders
        ];

        // Calculate statistics — consolidate 6 queries into 2.
        // When a shop is selected, attribute sales to the shop that OWNS each
        // product line (sale_items.shop_id), not the shop the sale was rung up
        // under. A sale that mixes shops therefore counts toward each shop.
        if ($selectedShopId !== null) {
            $saleStats = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sale_items.shop_id', $selectedShopId)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN sales.status = 'completed' AND sales.created_at BETWEEN ? AND ? THEN sale_items.line_total ELSE 0 END), 0) as current_sales,
                    COALESCE(SUM(CASE WHEN sales.status = 'completed' AND sales.created_at BETWEEN ? AND ? THEN sale_items.unit_cost * sale_items.quantity ELSE 0 END), 0) as current_cost,
                    COUNT(DISTINCT CASE WHEN sales.created_at BETWEEN ? AND ? THEN sales.id END) as current_orders,
                    COALESCE(SUM(CASE WHEN sales.status = 'completed' AND sales.created_at BETWEEN ? AND ? THEN sale_items.line_total ELSE 0 END), 0) as last_sales,
                    COALESCE(SUM(CASE WHEN sales.status = 'completed' AND sales.created_at BETWEEN ? AND ? THEN sale_items.unit_cost * sale_items.quantity ELSE 0 END), 0) as last_cost,
                    COUNT(DISTINCT CASE WHEN sales.created_at BETWEEN ? AND ? THEN sales.id END) as last_orders
                ", $ranges)
                ->first();
        } else {
            $saleStatsQuery = DB::table('sales')->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'completed' AND created_at BETWEEN ? AND ? THEN total_amount ELSE 0 END), 0) as current_sales,
                COALESCE(SUM(CASE WHEN status = 'completed' AND created_at BETWEEN ? AND ? THEN total_cost ELSE 0 END), 0) as current_cost,
                COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) as current_orders,
                COALESCE(SUM(CASE WHEN status = 'completed' AND created_at BETWEEN ? AND ? THEN total_amount ELSE 0 END), 0) as last_sales,
                COALESCE(SUM(CASE WHEN status = 'completed' AND created_at BETWEEN ? AND ? THEN total_cost ELSE 0 END), 0) as last_cost,
                COUNT(CASE WHEN created_at BETWEEN ? AND ? THEN 1 END) as last_orders
            ", $ranges);

            $saleStats = $this->applyShopFilter($saleStatsQuery, $user, $selectedShopId)->first();
        }

        $expenseStatsQuery = DB::table('expenses')->selectRaw('
            COALESCE(SUM(CASE WHEN expense_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) as current_expenses,
            COALESCE(SUM(CASE WHEN expense_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) as last_expenses
        ', [
            $startOfMonth,
            $endOfMonth,
            $startOfLastMonth,
            $endOfLastMonth,
        ]);

        $expenseStats = $this->applyShopFilter($expenseStatsQuery, $user, $selectedShopId)->first();

        // Net profit must clear the cost of the goods before operating expenses
        // are taken off. Revenue minus expenses alone reports the entire COGS as
        // profit, which is what made this tile read as a large gain on a
        // trading loss.
        $grossProfit = $saleStats->current_sales - $saleStats->current_cost;

        $statistics = [
            'total_sales' => $saleStats->current_sales,
            'total_cost' => $saleStats->current_cost,
            'total_expenses' => $expenseStats->current_expenses,
            'total_orders' => $saleStats->current_orders,
            'gross_profit' => $grossProfit,
            'profit' => $grossProfit - $expenseStats->current_expenses,
        ];

        $lastMonthSales = $saleStats->last_sales;
        $lastMonthExpenses = $expenseStats->last_expenses;
        $lastMonthOrders = $saleStats->last_orders;

        $statistics['sales_change'] = $lastMonthSales > 0
            ? (($statistics['total_sales'] - $lastMonthSales) / $lastMonthSales) * 100
            : 0;

        $statistics['expenses_change'] = $lastMonthExpenses > 0
            ? (($statistics['total_expenses'] - $lastMonthExpenses) / $lastMonthExpenses) * 100
            : 0;

        $statistics['orders_change'] = $lastMonthOrders > 0
            ? (($statistics['total_orders'] - $lastMonthOrders) / $lastMonthOrders) * 100
            : 0;

        $lastMonthProfit = ($lastMonthSales - $saleStats->last_cost) - $lastMonthExpenses;
        $statistics['profit_change'] = $lastMonthProfit > 0
            ? (($statistics['profit'] - $lastMonthProfit) / $lastMonthProfit) * 100
            : 0;

        // Recent Orders
        $recentOrders = Sale::with('customer:id,name')
            ->select('id', 'customer_id', 'total_amount', 'status', 'created_at')
            ->visibleTo($user)
            ->when($selectedShopId, fn ($query): mixed => $query->whereHas('items', fn ($itemQuery) => $itemQuery->where('shop_id', $selectedShopId)))
            ->latest('created_at')
            ->take(10)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'order_number' => str_pad($sale->id, 6, '0', STR_PAD_LEFT),
                    'date' => $sale->created_at->format('M d, Y'),
                    'customer_name' => $sale->customer->name ?? 'Walk-in Customer',
                    'amount' => $sale->total_amount,
                    'status' => $sale->status,
                ];
            });

        // Recent Payments — reads `sale_payments`, the table the sale flow writes.
        // This previously queried the unused `payments` table, so the panel was
        // always empty. sale_payments has no shop_id of its own, so scoping goes
        // through the parent sale.
        $recentPayments = SalePayment::query()
            ->with('sale:id,uuid,shop_id,invoice_number')
            ->visibleTo($user)
            ->when(
                $selectedShopId !== null,
                fn (EloquentBuilder $q) => $q->whereHas('sale', fn (EloquentBuilder $s) => $s->where('shop_id', $selectedShopId))
            )
            ->latest('paid_at')
            ->take(10)
            ->get()
            ->map(fn (SalePayment $payment): array => [
                'uuid' => $payment->uuid,
                'reference' => $payment->reference ?: $payment->payment_number,
                'date' => $payment->paid_at?->format('M d, Y'),
                'amount' => $payment->amount,
                'method' => $payment->payment_method_label,
                // sale_payments records money actually received; there is no
                // pending/failed state on it.
                'status' => 'verified',
            ]);

        // Low Stock Products — scoped to the product's owning shop.
        $lowStockProductsQuery = Product::query()
            ->where('products.status', 'active')
            ->select('products.id', 'products.name', 'products.sku', 'products.stock_quantity')
            ->whereRaw('products.stock_quantity <= products.reorder_level')
            ->orderBy('products.stock_quantity', 'asc')
            ->take(5);

        $this->applyShopFilter($lowStockProductsQuery, $user, $selectedShopId, 'products.shop_id');

        $lowStockProducts = $lowStockProductsQuery->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'stock_quantity' => $product->stock_quantity,
                ];
            });

        // Top Selling Products
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'products.id',
                'products.name',
                'categories.name as category',
                DB::raw('COUNT(sale_items.id) as sales_count'),
                DB::raw('SUM(sale_items.line_total) as revenue')
            )
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$startOfMonth, $endOfMonth])
            ->tap(fn ($query) => $this->applyShopFilter($query, $user, $selectedShopId, 'sale_items.shop_id'))
            ->groupBy('products.id', 'products.name', 'categories.name')
            ->orderByDesc('sales_count')
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'category' => $item->category ?? 'Uncategorized',
                    'sales_count' => $item->sales_count,
                    'revenue' => $item->revenue,
                ];
            });

        // Sales by Category
        $categoryStats = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.name',
                DB::raw('SUM(sale_items.line_total) as sales')
            )
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$startOfMonth, $endOfMonth])
            ->tap(fn ($query) => $this->applyShopFilter($query, $user, $selectedShopId, 'sale_items.shop_id'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('sales')
            ->take(5)
            ->get();

        $totalCategorySales = $categoryStats->sum('sales');
        $categoryStats = $categoryStats->map(function ($item) use ($totalCategorySales) {
            return [
                'name' => $item->name,
                'sales' => $item->sales,
                'percentage' => $totalCategorySales > 0 ? ($item->sales / $totalCategorySales) * 100 : 0,
            ];
        });

        // Chart Data - Last 12 months (2 queries instead of 24)
        $twelveMonthsAgo = Carbon::now()->subMonths(11)->startOfMonth();

        $salesMonthExpression = $this->monthExpression('created_at');
        $expensesMonthExpression = $this->monthExpression('expense_date');

        if ($selectedShopId !== null) {
            // Item-level monthly revenue for the selected shop.
            $itemMonthExpression = $this->monthExpression('sales.created_at');
            $monthlySales = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sale_items.shop_id', $selectedShopId)
                ->where('sales.created_at', '>=', $twelveMonthsAgo)
                ->selectRaw("{$itemMonthExpression} as month, COALESCE(SUM(CASE WHEN sales.status = 'completed' THEN sale_items.line_total ELSE 0 END), 0) as total")
                ->groupByRaw($itemMonthExpression)
                ->pluck('total', 'month');
        } else {
            $monthlySales = DB::table('sales')
                ->selectRaw("{$salesMonthExpression} as month, COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) as total")
                ->where('created_at', '>=', $twelveMonthsAgo)
                ->tap(fn ($query) => $this->applyShopFilter($query, $user, $selectedShopId))
                ->groupByRaw($salesMonthExpression)
                ->pluck('total', 'month');
        }

        $monthlyExpenses = DB::table('expenses')
            ->selectRaw("{$expensesMonthExpression} as month, COALESCE(SUM(amount), 0) as total")
            ->where('expense_date', '>=', $twelveMonthsAgo)
            ->tap(fn ($query) => $this->applyShopFilter($query, $user, $selectedShopId))
            ->groupByRaw($expensesMonthExpression)
            ->pluck('total', 'month');

        $months = [];
        $salesData = [];
        $expensesData = [];

        for ($i = 11; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i)->startOfMonth();
            $key = $monthDate->format('Y-m');
            $months[] = $monthDate->format('M Y');
            $salesData[] = (float) ($monthlySales[$key] ?? 0);
            $expensesData[] = (float) ($monthlyExpenses[$key] ?? 0);
        }

        $chartData = [
            'months' => $months,
            'sales' => $salesData,
            'expenses' => $expensesData,
            'category_labels' => $categoryStats->pluck('name')->toArray(),
            'category_values' => $categoryStats->pluck('sales')->toArray(),
        ];

        // Processing Ecommerce Orders Count
        $processingOrdersCount = EcommerceOrder::visibleTo($user)
            ->when($selectedShopId, fn ($query): mixed => $query->where('shop_id', $selectedShopId))
            ->where('status', EcommerceOrderStatus::Processing)
            ->count();

        return view('dashboard', compact(
            'statistics',
            'recentOrders',
            'recentPayments',
            'lowStockProducts',
            'topProducts',
            'categoryStats',
            'chartData',
            'processingOrdersCount',
            'shops',
            'selectedShopId'
        ));
    }

    private function applyShopFilter(
        EloquentBuilder|QueryBuilder $query,
        User $user,
        ?int $shopId,
        string $column = 'shop_id'
    ): EloquentBuilder|QueryBuilder {
        if ($shopId !== null) {
            return $query->where($column, $shopId);
        }

        if ($user->hasShopRestrictions()) {
            return $query->whereIn($column, $user->accessibleShopIds());
        }

        return $query;
    }

    private function monthExpression(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "strftime('%Y-%m', {$column})";
        }

        return "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
