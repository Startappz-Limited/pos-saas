<?php

namespace App\Enums;

enum ReturnReason: string
{
    case DEFECTIVE = 'defective';
    case DAMAGED = 'damaged';
    case WRONG_ITEM = 'wrong_item';
    case EXPIRED = 'expired';
    case CUSTOMER_CHANGED_MIND = 'customer_changed_mind';
    case NOT_AS_DESCRIBED = 'not_as_described';
    case QUALITY_ISSUE = 'quality_issue';
    case DUPLICATE_ORDER = 'duplicate_order';
    case NO_LONGER_NEEDED = 'no_longer_needed';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DEFECTIVE => 'Defective Product',
            self::DAMAGED => 'Damaged During Shipping',
            self::WRONG_ITEM => 'Wrong Item Received',
            self::EXPIRED => 'Expired Product',
            self::CUSTOMER_CHANGED_MIND => 'Customer Changed Mind',
            self::NOT_AS_DESCRIBED => 'Not As Described',
            self::QUALITY_ISSUE => 'Quality Issue',
            self::DUPLICATE_ORDER => 'Duplicate Order',
            self::NO_LONGER_NEEDED => 'No Longer Needed',
            self::OTHER => 'Other',
        };
    }

    public function requiresInspection(): bool
    {
        return in_array($this, [
            self::DEFECTIVE,
            self::DAMAGED,
            self::QUALITY_ISSUE,
            self::EXPIRED,
        ]);
    }

    public function isRestockable(): bool
    {
        return in_array($this, [
            self::CUSTOMER_CHANGED_MIND,
            self::WRONG_ITEM,
            self::DUPLICATE_ORDER,
            self::NO_LONGER_NEEDED,
        ]);
    }
}
