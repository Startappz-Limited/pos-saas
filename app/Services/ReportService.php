<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Sale;
use App\Models\Shop;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getDashboardData(array $filters): array
    {
        $startDate = Carbon::parse($filters['start_date'])->startOfDay();
        $endDate = Carbon::parse($filters['end_date'])->endOfDay();

        $salesQuery = Sale::query()
            ->with(['shop:id,name', 'customer:id,name,customer_type']);

        $this->applySaleFilters($salesQuery, $filters, $startDate, $endDate);

        $expensesQuery = Expense::query()
            ->with(['shop:id,name', 'category:id,name'])
            ->whereDate('expense_date', '>=', $startDate->toDateString())
            ->whereDate('expense_date', '<=', $endDate->toDateString());

        $this->applyExpenseFilters($expensesQuery, $filters);

        $expenseBaseQuery = DB::table('expenses')
            ->whereNull('deleted_at')
            ->whereDate('expense_date', '>=', $startDate->toDateString())
            ->whereDate('expense_date', '<=', $endDate->toDateString());

        $this->applyExpenseFiltersToBaseQuery($expenseBaseQuery, $filters);

        $salesSummary = (clone $salesQuery)
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(total_cost), 0) as total_cost')
            ->selectRaw('COALESCE(SUM(total_profit), 0) as gross_profit')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as total_discounts')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as total_tax')
            ->selectRaw('COALESCE(SUM(delivery_fee + packaging_fee + other_expenses), 0) as total_fees')
            ->first();

        $expensesSummary = (clone $expenseBaseQuery)
            ->selectRaw('COUNT(*) as total_expenses')
            ->selectRaw('COALESCE(SUM(amount), 0) as expense_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as approved_expenses', ['approved'])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as paid_expenses', ['paid'])
            ->first();

        $wholesaleQuery = clone $salesQuery;
        $this->applyWholesaleCondition($wholesaleQuery);
        $wholesaleSummary = $wholesaleQuery
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(total_profit), 0) as gross_profit')
            ->first();

        $retailQuery = clone $salesQuery;
        $this->applyRetailCondition($retailQuery);
        $retailSummary = $retailQuery
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(total_profit), 0) as gross_profit')
            ->first();

        $salesByShop = $this->getSalesByShopFromItems($filters, $startDate, $endDate);

        $expensesByShop = (clone $expenseBaseQuery)
            ->groupBy('shop_id')
            ->orderByDesc(DB::raw('SUM(amount)'))
            ->selectRaw('shop_id as shop_id')
            ->selectRaw('COUNT(*) as total_expenses')
            ->selectRaw('COALESCE(SUM(amount), 0) as expense_total')
            ->get();

        $shopNames = Shop::query()
            ->get(['id', 'name'])
            ->pluck('name', 'id');

        $shopPerformance = $salesByShop
            ->map(function ($row) use ($expensesByShop, $shopNames) {
                $expenseRow = $expensesByShop->firstWhere('shop_id', $row->shop_id);
                $expenseTotal = (float) ($expenseRow->expense_total ?? 0);
                $netProfit = (float) $row->gross_profit - $expenseTotal;
                $netMargin = (float) $row->revenue > 0 ? ($netProfit / (float) $row->revenue) * 100 : 0;

                return [
                    'shop_id' => (int) $row->shop_id,
                    'shop_name' => $shopNames[(int) $row->shop_id] ?? 'Unknown Shop',
                    'transactions' => (int) $row->transactions,
                    'revenue' => (float) $row->revenue,
                    'cost' => (float) $row->cost,
                    'gross_profit' => (float) $row->gross_profit,
                    'expense_total' => $expenseTotal,
                    'net_profit' => $netProfit,
                    'net_margin' => $netMargin,
                ];
            })
            ->values();

        $expensesByCategoryRows = (clone $expenseBaseQuery)
            ->groupBy('category_id')
            ->orderByDesc(DB::raw('SUM(amount)'))
            ->selectRaw('category_id')
            ->selectRaw('COUNT(*) as total_expenses')
            ->selectRaw('COALESCE(SUM(amount), 0) as expense_total')
            ->limit(10)
            ->get();

        $categoryNames = ExpenseCategory::query()
            ->get(['id', 'name'])
            ->pluck('name', 'id');

        $expensesByCategory = $expensesByCategoryRows
            ->map(function ($row) use ($categoryNames) {
                return (object) [
                    'category_name' => $row->category_id ? ($categoryNames[(int) $row->category_id] ?? 'Uncategorized') : 'Uncategorized',
                    'total_expenses' => (int) $row->total_expenses,
                    'expense_total' => (float) $row->expense_total,
                ];
            })
            ->values();

        $topProductsByMargin = $this->getTopProductsByMargin($filters, $startDate, $endDate);

        $dailyTrend = $this->getDailyTrend($filters, $startDate, $endDate);

        $recentSales = (clone $salesQuery)
            ->latest('created_at')
            ->limit(12)
            ->get();

        $recentExpenses = (clone $expensesQuery)
            ->latest('expense_date')
            ->limit(12)
            ->get();

        $totalRevenue = (float) ($salesSummary?->total_revenue ?? 0);
        $grossProfit = (float) ($salesSummary?->gross_profit ?? 0);
        $expenseTotal = (float) ($expensesSummary?->expense_total ?? 0);
        $netProfit = $grossProfit - $expenseTotal;

        return [
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'shop_id' => $filters['shop_id'] ?? null,
                'sale_status' => $filters['sale_status'] ?? 'all',
                'sale_type' => $filters['sale_type'] ?? 'all',
                'customer_segment' => $filters['customer_segment'] ?? 'all',
                'payment_status' => $filters['payment_status'] ?? 'all',
                'expense_status' => $filters['expense_status'] ?? 'all',
            ],
            'summary' => [
                'total_transactions' => (int) ($salesSummary?->total_transactions ?? 0),
                'total_revenue' => $totalRevenue,
                'total_cost' => (float) ($salesSummary?->total_cost ?? 0),
                'gross_profit' => $grossProfit,
                'gross_margin' => $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0,
                'total_discounts' => (float) ($salesSummary?->total_discounts ?? 0),
                'total_tax' => (float) ($salesSummary?->total_tax ?? 0),
                'total_fees' => (float) ($salesSummary?->total_fees ?? 0),
                'expense_total' => $expenseTotal,
                'net_profit' => $netProfit,
                'net_margin' => $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0,
                'total_expenses' => (int) ($expensesSummary?->total_expenses ?? 0),
                'approved_expenses' => (float) ($expensesSummary?->approved_expenses ?? 0),
                'paid_expenses' => (float) ($expensesSummary?->paid_expenses ?? 0),
            ],
            'wholesale' => [
                'transactions' => (int) ($wholesaleSummary?->total_transactions ?? 0),
                'revenue' => (float) ($wholesaleSummary?->total_revenue ?? 0),
                'gross_profit' => (float) ($wholesaleSummary?->gross_profit ?? 0),
                'margin' => (float) ($wholesaleSummary?->total_revenue ?? 0) > 0
                    ? ((float) ($wholesaleSummary?->gross_profit ?? 0) / (float) $wholesaleSummary->total_revenue) * 100
                    : 0,
            ],
            'retail' => [
                'transactions' => (int) ($retailSummary?->total_transactions ?? 0),
                'revenue' => (float) ($retailSummary?->total_revenue ?? 0),
                'gross_profit' => (float) ($retailSummary?->gross_profit ?? 0),
                'margin' => (float) ($retailSummary?->total_revenue ?? 0) > 0
                    ? ((float) ($retailSummary?->gross_profit ?? 0) / (float) $retailSummary->total_revenue) * 100
                    : 0,
            ],
            'shop_performance' => $shopPerformance,
            'expenses_by_category' => $expensesByCategory,
            'top_products_by_margin' => $topProductsByMargin,
            'daily_trend' => $dailyTrend,
            'recent_sales' => $recentSales,
            'recent_expenses' => $recentExpenses,
        ];
    }

    protected function applySaleFilters(Builder $query, array $filters, Carbon $startDate, Carbon $endDate): void
    {
        $query->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString());

        // Scope by the shop that OWNS the products sold (item level), so a sale
        // is included for a shop when it contains that shop's products — not by
        // the shop the sale was rung up under.
        if (! empty($filters['allowed_shop_ids'])) {
            $allowedShopIds = $filters['allowed_shop_ids'];
            $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->whereIn('shop_id', $allowedShopIds));
        }

        if (! empty($filters['shop_id'])) {
            $shopId = $filters['shop_id'];
            $query->whereHas('items', fn (Builder $itemQuery) => $itemQuery->where('shop_id', $shopId));
        }

        if (($filters['sale_status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['sale_status']);
        }

        if (($filters['payment_status'] ?? 'all') !== 'all') {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (($filters['sale_type'] ?? 'all') !== 'all') {
            $query->where('sale_type', $filters['sale_type']);
        }

        if (($filters['customer_segment'] ?? 'all') === 'wholesale') {
            $this->applyWholesaleCondition($query);
        }

        if (($filters['customer_segment'] ?? 'all') === 'retail') {
            $this->applyRetailCondition($query);
        }
    }

    protected function applyExpenseFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['allowed_shop_ids'])) {
            $query->whereIn('shop_id', $filters['allowed_shop_ids']);
        }

        if (! empty($filters['shop_id'])) {
            $query->where('shop_id', $filters['shop_id']);
        }

        if (($filters['expense_status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['expense_status']);
        }
    }

    protected function applyExpenseFiltersToBaseQuery(QueryBuilder $query, array $filters): void
    {
        if (! empty($filters['allowed_shop_ids'])) {
            $query->whereIn('shop_id', $filters['allowed_shop_ids']);
        }

        if (! empty($filters['shop_id'])) {
            $query->where('shop_id', $filters['shop_id']);
        }

        if (($filters['expense_status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['expense_status']);
        }
    }

    protected function applyWholesaleCondition(Builder $query): void
    {
        $query->where(function (Builder $builder) {
            $builder->where('sale_type', 'wholesale')
                ->orWhereExists(function ($subQuery) {
                    $subQuery->selectRaw('1')
                        ->from('customers')
                        ->whereColumn('customers.id', 'sales.customer_id')
                        ->where('customers.customer_type', 'wholesale')
                        ->whereNull('customers.deleted_at');
                });
        });
    }

    protected function applyRetailCondition(Builder $query): void
    {
        $query->where(function (Builder $builder) {
            $builder->where('sale_type', '!=', 'wholesale')
                ->where(function (Builder $nested) {
                    $nested->whereNull('customer_id')
                        ->orWhereExists(function ($subQuery) {
                            $subQuery->selectRaw('1')
                                ->from('customers')
                                ->whereColumn('customers.id', 'sales.customer_id')
                                ->where('customers.customer_type', '!=', 'wholesale')
                                ->whereNull('customers.deleted_at');
                        });
                });
        });
    }

    protected function getTopProductsByMargin(array $filters, Carbon $startDate, Carbon $endDate)
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id');

        $this->applySaleItemFilters($query, $filters, $startDate, $endDate);

        return $query
            ->groupBy('sale_items.product_id', 'products.name')
            ->orderByDesc(DB::raw('SUM(sale_items.profit)'))
            ->selectRaw('products.name as product_name')
            ->selectRaw('COALESCE(SUM(sale_items.quantity), 0) as quantity_sold')
            ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(sale_items.total_cost), 0) as cost')
            ->selectRaw('COALESCE(SUM(sale_items.profit), 0) as gross_profit')
            ->selectRaw('CASE WHEN SUM(sale_items.line_total) > 0 THEN (SUM(sale_items.profit) / SUM(sale_items.line_total)) * 100 ELSE 0 END as margin')
            ->limit(10)
            ->get();
    }

    /**
     * Aggregate sales per shop at the line-item level so that a single sale
     * spanning multiple shops is attributed to each shop correctly.
     *
     * Returns rows shaped like the previous sale-level aggregation
     * (shop_id, transactions, revenue, cost, gross_profit) so the dashboard
     * mapping needs no changes.
     */
    protected function getSalesByShopFromItems(array $filters, Carbon $startDate, Carbon $endDate)
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id');

        $this->applySaleItemFilters($query, $filters, $startDate, $endDate);

        return $query
            ->groupBy('sale_items.shop_id')
            ->orderByDesc(DB::raw('SUM(sale_items.line_total)'))
            ->selectRaw('sale_items.shop_id as shop_id')
            ->selectRaw('COUNT(DISTINCT sale_items.sale_id) as transactions')
            ->selectRaw('COALESCE(SUM(sale_items.line_total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(sale_items.total_cost), 0) as cost')
            ->selectRaw('COALESCE(SUM(sale_items.profit), 0) as gross_profit')
            ->get();
    }

    /**
     * Apply the shared sale filters to a query joined on sale_items + sales.
     * Shop scoping is applied on sale_items.shop_id so product sales attribute
     * to the shop that owns each product, not the sale's primary shop.
     */
    protected function applySaleItemFilters(QueryBuilder $query, array $filters, Carbon $startDate, Carbon $endDate): void
    {
        $query->whereDate('sales.created_at', '>=', $startDate->toDateString())
            ->whereDate('sales.created_at', '<=', $endDate->toDateString());

        if (! empty($filters['allowed_shop_ids'])) {
            $query->whereIn('sale_items.shop_id', $filters['allowed_shop_ids']);
        }

        if (! empty($filters['shop_id'])) {
            $query->where('sale_items.shop_id', $filters['shop_id']);
        }

        if (($filters['sale_status'] ?? 'all') !== 'all') {
            $query->where('sales.status', $filters['sale_status']);
        }

        if (($filters['payment_status'] ?? 'all') !== 'all') {
            $query->where('sales.payment_status', $filters['payment_status']);
        }

        if (($filters['sale_type'] ?? 'all') !== 'all') {
            $query->where('sales.sale_type', $filters['sale_type']);
        }

        if (($filters['customer_segment'] ?? 'all') === 'wholesale') {
            $query->where(function ($builder) {
                $builder->where('sales.sale_type', 'wholesale')
                    ->orWhereExists(function ($subQuery) {
                        $subQuery->selectRaw('1')
                            ->from('customers')
                            ->whereColumn('customers.id', 'sales.customer_id')
                            ->where('customers.customer_type', 'wholesale');
                    });
            });
        }

        if (($filters['customer_segment'] ?? 'all') === 'retail') {
            $query->where('sales.sale_type', '!=', 'wholesale')
                ->where(function ($builder) {
                    $builder->whereNull('sales.customer_id')
                        ->orWhereExists(function ($subQuery) {
                            $subQuery->selectRaw('1')
                                ->from('customers')
                                ->whereColumn('customers.id', 'sales.customer_id')
                                ->where('customers.customer_type', '!=', 'wholesale');
                        });
                });
        }
    }

    protected function getDailyTrend(array $filters, Carbon $startDate, Carbon $endDate): array
    {
        $salesTrendQuery = DB::table('sales')
            ->whereDate('created_at', '>=', $startDate->toDateString())
            ->whereDate('created_at', '<=', $endDate->toDateString());

        if (! empty($filters['allowed_shop_ids'])) {
            $allowedShopIds = $filters['allowed_shop_ids'];
            $salesTrendQuery->whereExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                ->from('sale_items')
                ->whereColumn('sale_items.sale_id', 'sales.id')
                ->whereIn('sale_items.shop_id', $allowedShopIds));
        }

        if (! empty($filters['shop_id'])) {
            $shopId = $filters['shop_id'];
            $salesTrendQuery->whereExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                ->from('sale_items')
                ->whereColumn('sale_items.sale_id', 'sales.id')
                ->where('sale_items.shop_id', $shopId));
        }

        if (($filters['sale_status'] ?? 'all') !== 'all') {
            $salesTrendQuery->where('status', $filters['sale_status']);
        }

        if (($filters['payment_status'] ?? 'all') !== 'all') {
            $salesTrendQuery->where('payment_status', $filters['payment_status']);
        }

        if (($filters['sale_type'] ?? 'all') !== 'all') {
            $salesTrendQuery->where('sale_type', $filters['sale_type']);
        }

        $salesTrend = $salesTrendQuery
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(total_profit), 0) as gross_profit')
            ->get()
            ->keyBy('day');

        $expenseTrendQuery = DB::table('expenses')
            ->whereNull('deleted_at')
            ->whereDate('expense_date', '>=', $startDate->toDateString())
            ->whereDate('expense_date', '<=', $endDate->toDateString());

        if (! empty($filters['allowed_shop_ids'])) {
            $expenseTrendQuery->whereIn('shop_id', $filters['allowed_shop_ids']);
        }

        if (! empty($filters['shop_id'])) {
            $expenseTrendQuery->where('shop_id', $filters['shop_id']);
        }

        if (($filters['expense_status'] ?? 'all') !== 'all') {
            $expenseTrendQuery->where('status', $filters['expense_status']);
        }

        $expenseTrend = $expenseTrendQuery
            ->groupBy('expense_date')
            ->orderBy('expense_date')
            ->selectRaw('expense_date as day')
            ->selectRaw('COALESCE(SUM(amount), 0) as expenses')
            ->get()
            ->keyBy('day');

        $trend = [];

        foreach (CarbonPeriod::create($startDate->toDateString(), $endDate->toDateString()) as $date) {
            $day = $date->toDateString();
            $salesData = $salesTrend->get($day);
            $expenseData = $expenseTrend->get($day);

            $revenue = (float) ($salesData->revenue ?? 0);
            $grossProfit = (float) ($salesData->gross_profit ?? 0);
            $expenses = (float) ($expenseData->expenses ?? 0);

            $trend[] = [
                'day' => $day,
                'transactions' => (int) ($salesData->transactions ?? 0),
                'revenue' => $revenue,
                'gross_profit' => $grossProfit,
                'expenses' => $expenses,
                'net_profit' => $grossProfit - $expenses,
            ];
        }

        return $trend;
    }
}
