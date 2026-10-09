<?php

namespace App\Enums;

enum ReportType: string
{
    // Sales Reports
    case SALES_SUMMARY = 'sales_summary';
    case SALES_BY_PRODUCT = 'sales_by_product';
    case SALES_BY_CATEGORY = 'sales_by_category';
    case SALES_BY_CUSTOMER = 'sales_by_customer';
    case SALES_BY_STAFF = 'sales_by_staff';
    case SALES_TREND = 'sales_trend';

    // Inventory Reports
    case INVENTORY_VALUATION = 'inventory_valuation';
    case STOCK_MOVEMENT = 'stock_movement';
    case LOW_STOCK = 'low_stock';
    case EXPIRING_STOCK = 'expiring_stock';
    case DEAD_STOCK = 'dead_stock';

    // Financial Reports
    case PROFIT_LOSS = 'profit_loss';
    case EXPENSE_SUMMARY = 'expense_summary';
    case EXPENSE_BY_CATEGORY = 'expense_by_category';
    case CASH_FLOW = 'cash_flow';
    case TAX_SUMMARY = 'tax_summary';

    // Payment Reports
    case PAYMENTS_RECEIVED = 'payments_received';
    case OUTSTANDING_PAYMENTS = 'outstanding_payments';
    case CREDIT_AGING = 'credit_aging';

    // Supplier Reports
    case SUPPLIER_PURCHASES = 'supplier_purchases';
    case SUPPLIER_PAYMENTS = 'supplier_payments';

    public function label(): string
    {
        return match ($this) {
            self::SALES_SUMMARY => 'Sales Summary',
            self::SALES_BY_PRODUCT => 'Sales by Product',
            self::SALES_BY_CATEGORY => 'Sales by Category',
            self::SALES_BY_CUSTOMER => 'Sales by Customer',
            self::SALES_BY_STAFF => 'Sales by Staff',
            self::SALES_TREND => 'Sales Trend',
            self::INVENTORY_VALUATION => 'Inventory Valuation',
            self::STOCK_MOVEMENT => 'Stock Movement',
            self::LOW_STOCK => 'Low Stock Report',
            self::EXPIRING_STOCK => 'Expiring Stock',
            self::DEAD_STOCK => 'Dead Stock',
            self::PROFIT_LOSS => 'Profit & Loss',
            self::EXPENSE_SUMMARY => 'Expense Summary',
            self::EXPENSE_BY_CATEGORY => 'Expenses by Category',
            self::CASH_FLOW => 'Cash Flow',
            self::TAX_SUMMARY => 'Tax Summary',
            self::PAYMENTS_RECEIVED => 'Payments Received',
            self::OUTSTANDING_PAYMENTS => 'Outstanding Payments',
            self::CREDIT_AGING => 'Credit Aging Report',
            self::SUPPLIER_PURCHASES => 'Supplier Purchases',
            self::SUPPLIER_PAYMENTS => 'Supplier Payments',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::SALES_SUMMARY, self::SALES_BY_PRODUCT, self::SALES_BY_CATEGORY,
            self::SALES_BY_CUSTOMER, self::SALES_BY_STAFF, self::SALES_TREND => 'Sales',

            self::INVENTORY_VALUATION, self::STOCK_MOVEMENT, self::LOW_STOCK,
            self::EXPIRING_STOCK, self::DEAD_STOCK => 'Inventory',

            self::PROFIT_LOSS, self::EXPENSE_SUMMARY, self::EXPENSE_BY_CATEGORY,
            self::CASH_FLOW, self::TAX_SUMMARY => 'Financial',

            self::PAYMENTS_RECEIVED, self::OUTSTANDING_PAYMENTS, self::CREDIT_AGING => 'Payments',

            self::SUPPLIER_PURCHASES, self::SUPPLIER_PAYMENTS => 'Suppliers',
        };
    }

    public static function byCategory(): array
    {
        $grouped = [];
        foreach (self::cases() as $type) {
            $category = $type->category();
            $grouped[$category][] = $type;
        }

        return $grouped;
    }
}
