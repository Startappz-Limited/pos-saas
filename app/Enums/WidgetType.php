<?php

namespace App\Enums;

enum WidgetType: string
{
    // Summary Cards
    case TOTAL_SALES = 'total_sales';
    case TOTAL_REVENUE = 'total_revenue';
    case TOTAL_ORDERS = 'total_orders';
    case AVERAGE_ORDER = 'average_order';
    case TOTAL_CUSTOMERS = 'total_customers';
    case TOTAL_PRODUCTS = 'total_products';
    case LOW_STOCK_COUNT = 'low_stock_count';
    case PENDING_ORDERS = 'pending_orders';

    // Charts
    case SALES_CHART = 'sales_chart';
    case REVENUE_CHART = 'revenue_chart';
    case TOP_PRODUCTS_CHART = 'top_products_chart';
    case TOP_CATEGORIES_CHART = 'top_categories_chart';
    case EXPENSE_CHART = 'expense_chart';
    case PROFIT_CHART = 'profit_chart';

    // Tables/Lists
    case RECENT_SALES = 'recent_sales';
    case LOW_STOCK_LIST = 'low_stock_list';
    case RECENT_ALERTS = 'recent_alerts';
    case TOP_CUSTOMERS = 'top_customers';
    case PENDING_APPROVALS = 'pending_approvals';

    public function label(): string
    {
        return match ($this) {
            self::TOTAL_SALES => 'Total Sales',
            self::TOTAL_REVENUE => 'Total Revenue',
            self::TOTAL_ORDERS => 'Total Orders',
            self::AVERAGE_ORDER => 'Average Order Value',
            self::TOTAL_CUSTOMERS => 'Total Customers',
            self::TOTAL_PRODUCTS => 'Total Products',
            self::LOW_STOCK_COUNT => 'Low Stock Items',
            self::PENDING_ORDERS => 'Pending Orders',
            self::SALES_CHART => 'Sales Chart',
            self::REVENUE_CHART => 'Revenue Chart',
            self::TOP_PRODUCTS_CHART => 'Top Products',
            self::TOP_CATEGORIES_CHART => 'Top Categories',
            self::EXPENSE_CHART => 'Expenses Chart',
            self::PROFIT_CHART => 'Profit Chart',
            self::RECENT_SALES => 'Recent Sales',
            self::LOW_STOCK_LIST => 'Low Stock Items',
            self::RECENT_ALERTS => 'Recent Alerts',
            self::TOP_CUSTOMERS => 'Top Customers',
            self::PENDING_APPROVALS => 'Pending Approvals',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::TOTAL_SALES, self::TOTAL_REVENUE, self::TOTAL_ORDERS,
            self::AVERAGE_ORDER, self::TOTAL_CUSTOMERS, self::TOTAL_PRODUCTS,
            self::LOW_STOCK_COUNT, self::PENDING_ORDERS => 'card',

            self::SALES_CHART, self::REVENUE_CHART, self::TOP_PRODUCTS_CHART,
            self::TOP_CATEGORIES_CHART, self::EXPENSE_CHART, self::PROFIT_CHART => 'chart',

            self::RECENT_SALES, self::LOW_STOCK_LIST, self::RECENT_ALERTS,
            self::TOP_CUSTOMERS, self::PENDING_APPROVALS => 'list',
        };
    }

    public function defaultSize(): string
    {
        return match ($this->category()) {
            'card' => 'small',
            'chart' => 'large',
            'list' => 'medium',
        };
    }
}
