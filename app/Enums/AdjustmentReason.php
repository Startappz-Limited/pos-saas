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

    /**
     * Which way the reason moves stock (stage 10, step 1g): a line's signed
     * `quantity_change` must agree with it. "Recount" and "other" may go either
     * way, so one adjustment can raise some lines and lower others.
     *
     * @return 'increase'|'decrease'|'either'
     */
    public function direction(): string
    {
        return match ($this) {
            self::FOUND => 'increase',
            self::RECOUNT, self::OTHER => 'either',
            default => 'decrease',
        };
    }

    /**
     * Loss, theft, damage and expiry always need a second person to approve
     * (decided 27 October 2026), whatever the value; so do quality issues and
     * promotional giveaways (decided 3 November 2026).
     */
    public function needsSecondPerson(): bool
    {
        return in_array($this, [self::LOST, self::STOLEN, self::DAMAGED, self::EXPIRED, self::QUALITY_ISSUE, self::PROMOTIONAL_GIVEAWAY], true);
    }

    /**
     * The reasons a new adjustment may use: "returned to supplier" is a purchase
     * return (decided 27 October 2026), which keeps the credit the supplier owes;
     * existing adjustments with it stay as history.
     *
     * @return list<self>
     */
    public static function offered(): array
    {
        return array_values(array_filter(self::cases(), fn (self $reason): bool => $reason !== self::RETURNED_TO_SUPPLIER));
    }
}
