<?php

namespace App\Enums;

enum AlertCategory: string
{
    case INVENTORY = 'inventory';
    case SALES = 'sales';
    case PAYMENTS = 'payments';
    case EXPENSES = 'expenses';
    case CUSTOMERS = 'customers';
    case ORDERS = 'orders';
    case SYSTEM = 'system';
    case SECURITY = 'security';

    public function label(): string
    {
        return match ($this) {
            self::INVENTORY => 'Inventory',
            self::SALES => 'Sales',
            self::PAYMENTS => 'Payments',
            self::EXPENSES => 'Expenses',
            self::CUSTOMERS => 'Customers',
            self::ORDERS => 'Orders',
            self::SYSTEM => 'System',
            self::SECURITY => 'Security',
        };
    }

    public function types(): array
    {
        return match ($this) {
            self::INVENTORY => [
                AlertType::LOW_STOCK,
                AlertType::OUT_OF_STOCK,
                AlertType::STOCK_EXPIRING,
                AlertType::REORDER_POINT,
            ],
            self::SALES => [
                AlertType::SALE_COMPLETED,
                AlertType::LARGE_SALE,
                AlertType::REFUND_PROCESSED,
                AlertType::SALES_TARGET_MET,
            ],
            self::PAYMENTS => [
                AlertType::PAYMENT_RECEIVED,
                AlertType::PAYMENT_OVERDUE,
                AlertType::CREDIT_LIMIT_WARNING,
                AlertType::CREDIT_LIMIT_EXCEEDED,
            ],
            self::EXPENSES => [
                AlertType::EXPENSE_PENDING,
                AlertType::EXPENSE_APPROVED,
                AlertType::BUDGET_WARNING,
                AlertType::BUDGET_EXCEEDED,
            ],
            self::ORDERS => [
                AlertType::ORDER_NOTE,
                AlertType::ORDER_REMINDER,
                AlertType::ORDER_STATUS_CHANGED,
                AlertType::CART_ACTIVITY,
                AlertType::CART_NOTE,
                AlertType::CART_REMINDER,
            ],
            self::SYSTEM => [
                AlertType::SYSTEM_ERROR,
                AlertType::BACKUP_COMPLETED,
            ],
            self::SECURITY => [
                AlertType::USER_LOGIN,
                AlertType::PASSWORD_CHANGED,
            ],
            self::CUSTOMERS => [],
        };
    }
}
