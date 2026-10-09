<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';
    case CHEQUE = 'cheque';
    case MOBILE_MONEY = 'mobile_money';
    case CREDIT = 'credit';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Cash',
            self::CARD => 'Card',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::CHEQUE => 'Cheque',
            self::MOBILE_MONEY => 'Mobile Money',
            self::CREDIT => 'Credit/On Account',
            self::OTHER => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CASH => 'money-dollar',
            self::CARD => 'credit-card',
            self::BANK_TRANSFER => 'bank',
            self::CHEQUE => 'file-text',
            self::MOBILE_MONEY => 'smartphone',
            self::CREDIT => 'wallet',
            self::OTHER => 'more',
        };
    }

    public function requiresReference(): bool
    {
        return in_array($this, [
            self::CARD,
            self::BANK_TRANSFER,
            self::CHEQUE,
            self::MOBILE_MONEY,
        ]);
    }

    public function requiresVerification(): bool
    {
        return in_array($this, [
            self::CHEQUE,
            self::BANK_TRANSFER,
        ]);
    }
}
