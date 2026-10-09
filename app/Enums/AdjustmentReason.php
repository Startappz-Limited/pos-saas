<?php

namespace App\Enums;

enum AdjustmentReason: string
{
    case DAMAGED = 'damaged';
    case EXPIRED = 'expired';
    case LOST = 'lost';
    case STOLEN = 'stolen';
    case FOUND = 'found';
    case RECOUNT = 'recount';
    case QUALITY_ISSUE = 'quality_issue';
    case RETURNED_TO_SUPPLIER = 'returned_to_supplier';
    case PROMOTIONAL_GIVEAWAY = 'promotional_giveaway';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DAMAGED => 'Damaged Goods',
            self::EXPIRED => 'Expired Products',
            self::LOST => 'Lost Inventory',
            self::STOLEN => 'Theft/Stolen',
            self::FOUND => 'Found During Count',
            self::RECOUNT => 'Physical Count Adjustment',
            self::QUALITY_ISSUE => 'Quality Issue',
            self::RETURNED_TO_SUPPLIER => 'Returned to Supplier',
            self::PROMOTIONAL_GIVEAWAY => 'Promotional Giveaway',
            self::OTHER => 'Other',
        };
    }

    public function isNegative(): bool
    {
        return in_array($this, [
            self::DAMAGED,
            self::EXPIRED,
            self::LOST,
            self::STOLEN,
            self::QUALITY_ISSUE,
            self::RETURNED_TO_SUPPLIER,
            self::PROMOTIONAL_GIVEAWAY,
        ]);
    }

    public function requiresApproval(): bool
    {
        return in_array($this, [
            self::LOST,
            self::STOLEN,
        ]);
    }
}
