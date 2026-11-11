<?php

namespace App\Support\Stock;

use App\Models\Shop;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Whether a batch is expired, in the shop's own local day (stage 11, step 5,
 * decided): from the start of the day after its `expiry_date`. A batch with
 * `expiry_date` 31 October is sellable until 23:59:59 on 31 October, shop
 * time, and expired from 00:00 on 1 November. `consume()`, the sync pull's
 * sellable/expired quantities, the daily touch, the expiring-stock list and
 * stage 12's recall report all read a batch's expiry through this class, so
 * they never disagree.
 *
 * Every method takes the shop so it reads `Shop::localTimezone()` (the
 * shop's own timezone, else the business's, else Africa/Nairobi — the same
 * fallback `VoidWindow` uses). `$at` is the moment to judge by: now for an
 * online check, a till's `recorded_at` for an offline one.
 */
final class Expiry
{
    /**
     * The shop's local date for $at (now, when not given), at midnight: the
     * instant a batch expiring that day is still sellable up to, but not
     * including, the day after.
     */
    public static function firstSellableDate(Shop $shop, ?CarbonInterface $at = null): CarbonImmutable
    {
        return ($at !== null ? CarbonImmutable::instance($at) : CarbonImmutable::now())
            ->setTimezone($shop->localTimezone())->startOfDay();
    }

    /**
     * The shop's local today, at midnight.
     */
    public static function localToday(Shop $shop): CarbonImmutable
    {
        return CarbonImmutable::now($shop->localTimezone())->startOfDay();
    }

    /**
     * The shop's local midnight that starts $date.
     */
    public static function localMidnight(Shop $shop, CarbonInterface|string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, $shop->localTimezone())->startOfDay();
    }

    /**
     * Whether a batch with this expiry date is expired at $shop, at $at (now
     * when not given). Null (no expiry) is never expired.
     */
    public static function isExpired(?string $expiryDate, Shop $shop, ?CarbonInterface $at = null): bool
    {
        if ($expiryDate === null) {
            return false;
        }

        return CarbonImmutable::parse($expiryDate)->lessThan(self::firstSellableDate($shop, $at));
    }

    /**
     * The SQL condition `consume()` filters batches by, in both consumption
     * modes: `expiry_date IS NULL OR expiry_date < firstSellableDate`.
     */
    public static function sellableCondition(Shop $shop, ?CarbonInterface $at = null): string
    {
        $boundary = self::firstSellableDate($shop, $at)->toDateString();

        return "expiry_date IS NULL OR expiry_date >= '{$boundary}'";
    }

    /**
     * The expiry-date range that newly expired between $from (null: the shop
     * has never run the touch before, so every expired date up to $now) and
     * $now, as a [lower, upper] pair for a `whereBetween`-style query — not
     * an enumerated list, since the touch command only needs the bounds. A
     * date D's expiry moment is the shop's local midnight after it, so D is
     * in range when that moment falls after $from and at or before $now.
     *
     * @return array{0: ?string, 1: string}
     */
    public static function expiredDateRange(Shop $shop, ?CarbonInterface $from, CarbonInterface $now): array
    {
        return [
            $from === null ? null : self::firstSellableDate($shop, $from)->subDay()->toDateString(),
            self::firstSellableDate($shop, $now)->subDay()->toDateString(),
        ];
    }

    /**
     * A month-only expiry ("10/2026", "MM/YYYY") as its last day; a full
     * date is returned as given (just normalised). Null stays null.
     */
    public static function parse(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})\/(\d{4})$/', trim($value), $matches) === 1) {
            return CarbonImmutable::createFromDate((int) $matches[2], (int) $matches[1], 1)->endOfMonth()->toDateString();
        }

        return CarbonImmutable::parse($value)->toDateString();
    }
}
