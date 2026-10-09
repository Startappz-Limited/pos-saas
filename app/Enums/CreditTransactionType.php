<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case PURCHASE = 'purchase';
    case PAYMENT = 'payment';
    case ADJUSTMENT_CREDIT = 'adjustment_credit';
    case ADJUSTMENT_DEBIT = 'adjustment_debit';
    case REFUND = 'refund';
    case WRITE_OFF = 'write_off';
    case OPENING_BALANCE = 'opening_balance';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase',
            self::PAYMENT => 'Payment',
            self::ADJUSTMENT_CREDIT => 'Credit Adjustment',
            self::ADJUSTMENT_DEBIT => 'Debit Adjustment',
            self::REFUND => 'Refund',
            self::WRITE_OFF => 'Write Off',
            self::OPENING_BALANCE => 'Opening Balance',
        };
    }

    public function isDebit(): bool
    {
        return in_array($this, [
            self::PURCHASE,
            self::ADJUSTMENT_DEBIT,
            self::OPENING_BALANCE,
        ]);
    }

    public function isCredit(): bool
    {
        return in_array($this, [
            self::PAYMENT,
            self::ADJUSTMENT_CREDIT,
            self::REFUND,
            self::WRITE_OFF,
        ]);
    }
}
