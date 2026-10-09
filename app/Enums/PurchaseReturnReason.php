<?php

namespace App\Enums;

enum PurchaseReturnReason: string
{
    case DEFECTIVE = 'defective';
    case DAMAGED = 'damaged';
    case WRONG_ITEM = 'wrong_item';
    case QUALITY_ISSUE = 'quality_issue';
    case EXPIRED = 'expired';
    case CUSTOMER_RETURN = 'customer_return';
    case EXCESS_STOCK = 'excess_stock';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DEFECTIVE => 'Defective Product',
            self::DAMAGED => 'Damaged Product',
            self::WRONG_ITEM => 'Wrong Item Supplied',
            self::QUALITY_ISSUE => 'Quality Issue',
            self::EXPIRED => 'Expired Product',
            self::CUSTOMER_RETURN => 'Customer Return',
            self::EXCESS_STOCK => 'Excess Stock',
            self::OTHER => 'Other',
        };
    }
}
