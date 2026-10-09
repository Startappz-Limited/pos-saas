<?php

namespace App\Enums;

enum StockMovementType: string
{
    case INTAKE = 'intake';
    case SALE = 'sale';
    case RETURN = 'return';
    case SUPPLIER_RETURN = 'supplier_return';
    case ADJUSTMENT_ADD = 'adjustment_add';
    case ADJUSTMENT_REMOVE = 'adjustment_remove';
    case DAMAGE = 'damage';
    case TRANSFER_IN = 'transfer_in';
    case TRANSFER_OUT = 'transfer_out';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::INTAKE => 'Stock Intake',
            self::SALE => 'Sale',
            self::RETURN => 'Customer Return',
            self::SUPPLIER_RETURN => 'Supplier Return',
            self::ADJUSTMENT_ADD => 'Adjustment (Add)',
            self::ADJUSTMENT_REMOVE => 'Adjustment (Remove)',
            self::DAMAGE => 'Damaged Stock',
            self::TRANSFER_IN => 'Transfer In',
            self::TRANSFER_OUT => 'Transfer Out',
            self::EXPIRED => 'Expired',
        };
    }

    public function isAddition(): bool
    {
        return in_array($this, [
            self::INTAKE,
            self::RETURN,
            self::ADJUSTMENT_ADD,
            self::TRANSFER_IN,
        ]);
    }

    public function isDeduction(): bool
    {
        return in_array($this, [
            self::SALE,
            self::SUPPLIER_RETURN,
            self::ADJUSTMENT_REMOVE,
            self::DAMAGE,
            self::TRANSFER_OUT,
            self::EXPIRED,
        ]);
    }
}
