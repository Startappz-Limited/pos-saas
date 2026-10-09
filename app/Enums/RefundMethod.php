<?php

namespace App\Enums;

enum RefundMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';
    case STORE_CREDIT = 'store_credit';
    case ORIGINAL_PAYMENT = 'original_payment';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash Refund',
            self::CARD => 'Card Refund',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::STORE_CREDIT => 'Store Credit',
            self::ORIGINAL_PAYMENT => 'Original Payment Method',
        };
    }

    public function requiresApproval(): bool
    {
        return in_array($this, [
            self::CASH,
            self::BANK_TRANSFER,
        ]);
    }
}
