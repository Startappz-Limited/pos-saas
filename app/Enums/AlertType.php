<?php

namespace App\Enums;

enum AlertType: string
{
    // Inventory
    case LOW_STOCK = 'low_stock';
    case OUT_OF_STOCK = 'out_of_stock';
    case STOCK_EXPIRING = 'stock_expiring';
    case REORDER_POINT = 'reorder_point';

    // Sales
    case SALE_COMPLETED = 'sale_completed';
    case LARGE_SALE = 'large_sale';
    case REFUND_PROCESSED = 'refund_processed';
    case SALES_TARGET_MET = 'sales_target_met';

    // Payments
    case PAYMENT_RECEIVED = 'payment_received';
    case PAYMENT_OVERDUE = 'payment_overdue';
    case CREDIT_LIMIT_WARNING = 'credit_limit_warning';
    case CREDIT_LIMIT_EXCEEDED = 'credit_limit_exceeded';

    // Expenses
    case EXPENSE_PENDING = 'expense_pending';
    case EXPENSE_APPROVED = 'expense_approved';
    case BUDGET_WARNING = 'budget_warning';
    case BUDGET_EXCEEDED = 'budget_exceeded';

    // Orders
    case ORDER_NOTE = 'order_note';
    case ORDER_REMINDER = 'order_reminder';
    case ORDER_STATUS_CHANGED = 'order_status_changed';

    // Abandoned carts (timeline of a website cart)
    case CART_ACTIVITY = 'cart_activity';
    case CART_NOTE = 'cart_note';
    case CART_REMINDER = 'cart_reminder';

    // System
    case USER_LOGIN = 'user_login';
    case PASSWORD_CHANGED = 'password_changed';
    case SYSTEM_ERROR = 'system_error';
    case BACKUP_COMPLETED = 'backup_completed';

    public function label(): string
    {
        return match ($this) {
            self::LOW_STOCK => 'Low Stock Alert',
            self::OUT_OF_STOCK => 'Out of Stock',
            self::STOCK_EXPIRING => 'Stock Expiring Soon',
            self::REORDER_POINT => 'Reorder Point Reached',
            self::SALE_COMPLETED => 'Sale Completed',
            self::LARGE_SALE => 'Large Sale Alert',
            self::REFUND_PROCESSED => 'Refund Processed',
            self::SALES_TARGET_MET => 'Sales Target Met',
            self::PAYMENT_RECEIVED => 'Payment Received',
            self::PAYMENT_OVERDUE => 'Payment Overdue',
            self::CREDIT_LIMIT_WARNING => 'Credit Limit Warning',
            self::CREDIT_LIMIT_EXCEEDED => 'Credit Limit Exceeded',
            self::EXPENSE_PENDING => 'Expense Pending Approval',
            self::EXPENSE_APPROVED => 'Expense Approved',
            self::BUDGET_WARNING => 'Budget Warning',
            self::BUDGET_EXCEEDED => 'Budget Exceeded',
            self::ORDER_NOTE => 'Order Note',
            self::ORDER_REMINDER => 'Order Reminder',
            self::ORDER_STATUS_CHANGED => 'Order Status Changed',
            self::CART_ACTIVITY => 'Cart Activity',
            self::CART_NOTE => 'Cart Note',
            self::CART_REMINDER => 'Cart Reminder',
            self::USER_LOGIN => 'User Login',
            self::PASSWORD_CHANGED => 'Password Changed',
            self::SYSTEM_ERROR => 'System Error',
            self::BACKUP_COMPLETED => 'Backup Completed',
        };
    }

    public function defaultSeverity(): AlertSeverity
    {
        return match ($this) {
            self::OUT_OF_STOCK,
            self::CREDIT_LIMIT_EXCEEDED,
            self::BUDGET_EXCEEDED,
            self::SYSTEM_ERROR => AlertSeverity::HIGH,

            self::LOW_STOCK,
            self::STOCK_EXPIRING,
            self::PAYMENT_OVERDUE,
            self::CREDIT_LIMIT_WARNING,
            self::BUDGET_WARNING => AlertSeverity::MEDIUM,

            self::ORDER_REMINDER => AlertSeverity::MEDIUM,
            self::ORDER_NOTE => AlertSeverity::LOW,
            self::ORDER_STATUS_CHANGED => AlertSeverity::LOW,

            self::CART_REMINDER => AlertSeverity::MEDIUM,
            self::CART_NOTE, self::CART_ACTIVITY => AlertSeverity::LOW,

            default => AlertSeverity::LOW,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::LOW_STOCK, self::OUT_OF_STOCK, self::REORDER_POINT => 'solar:box-bold',
            self::STOCK_EXPIRING => 'solar:calendar-bold',
            self::SALE_COMPLETED, self::LARGE_SALE => 'solar:cart-large-bold',
            self::REFUND_PROCESSED => 'solar:undo-left-bold',
            self::SALES_TARGET_MET => 'solar:cup-star-bold',
            self::PAYMENT_RECEIVED => 'solar:wallet-money-bold',
            self::PAYMENT_OVERDUE => 'solar:clock-circle-bold',
            self::CREDIT_LIMIT_WARNING, self::CREDIT_LIMIT_EXCEEDED => 'solar:card-bold',
            self::EXPENSE_PENDING, self::EXPENSE_APPROVED => 'solar:bill-list-bold',
            self::BUDGET_WARNING, self::BUDGET_EXCEEDED => 'solar:chart-bold',
            self::ORDER_NOTE => 'solar:document-text-bold',
            self::ORDER_REMINDER => 'solar:alarm-bold',
            self::ORDER_STATUS_CHANGED => 'solar:refresh-bold',
            self::CART_ACTIVITY => 'solar:cart-large-minimalistic-bold',
            self::CART_NOTE => 'solar:document-text-bold',
            self::CART_REMINDER => 'solar:alarm-bold',
            self::USER_LOGIN, self::PASSWORD_CHANGED => 'solar:user-bold',
            self::SYSTEM_ERROR => 'solar:danger-triangle-bold',
            self::BACKUP_COMPLETED => 'solar:cloud-download-bold',
        };
    }
}
