<?php

namespace App\Support\Sales;

use App\Models\Business;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How long a sale can be voided (decided 28 October 2026, stage 10, step 2),
 * from the business setting `sales.void_window`:
 *
 * - `session` (the default, and when the setting is missing): while the cash
 *   session the sale was made in is open, and never past 23:59:59 on the day it
 *   was completed; a sale with no session uses `end_of_day`;
 * - `end_of_day`: until 23:59:59 on the day it was completed;
 * - a whole number of minutes (1 to 1,440) from its completion;
 * - `none`: no limit.
 *
 * "The day" is the shop's local day (the shop's timezone, else the business's,
 * else Africa/Nairobi). A sale that isn't completed has no window: it can be
 * voided at any moment, and its window starts at its completion. The policy,
 * the sale page, `/api/v1` and the push all ask this class, so they agree; an
 * online void is checked at the current time, an offline one at its
 * `recorded_at`.
 */
final class VoidWindow
{
    public const SESSION = 'session';

    public const END_OF_DAY = 'end_of_day';

    public const NONE = 'none';

    public const MAX_MINUTES = 1440;

    public const DEFAULT_TIMEZONE = 'Africa/Nairobi';

    public const RETURNS_REFUSAL = 'This sale has returns. Process the return instead.';

    /**
     * Whether the sale has a return that isn't rejected (stage 10, step 4): it
     * can't be voided then.
     */
    public static function hasOpenReturns(Sale $sale): bool
    {
        return $sale->returns()->where('status', '!=', 'rejected')->exists();
    }

    /**
     * The business's setting: one of the constants, or a number of minutes.
     */
    public static function setting(?Business $business): string|int
    {
        $value = data_get($business?->settings, 'sales.void_window');

        if (in_array($value, [self::SESSION, self::END_OF_DAY, self::NONE], true)) {
            return $value;
        }

        if (is_numeric($value) && (int) $value == $value && (int) $value >= 1 && (int) $value <= self::MAX_MINUTES) {
            return (int) $value;
        }

        return self::SESSION;
    }

    /**
     * The last moment the sale can be voided, or null when there is no limit
     * (a sale that isn't completed, or the setting `none`). For a sale whose
     * session is still open this is the end of its day; allows() also checks
     * the session at the moment asked.
     */
    public function until(Sale $sale): ?CarbonImmutable
    {
        $completed = $this->completedAt($sale);
        $setting = $this->settingFor($sale);

        if ($completed === null || $setting === self::NONE) {
            return null;
        }

        if (is_int($setting)) {
            return $completed->addMinutes($setting);
        }

        $endOfDay = $completed->setTimezone($this->timezone($sale))->endOfDay()->setTimezone($completed->getTimezone());
        $closedAt = $setting === self::SESSION ? $this->sessionClosedAt($sale) : null;

        return $closedAt !== null && $closedAt->lessThan($endOfDay) ? $closedAt : $endOfDay;
    }

    /**
     * Whether the sale can be voided at $at: now for an online void, the push
     * item's `recorded_at` for an offline one.
     */
    public function allows(Sale $sale, CarbonInterface $at): bool
    {
        $until = $this->until($sale);
        if ($until === null) {
            return true;
        }

        $closedAt = $this->settingFor($sale) === self::SESSION ? $this->sessionClosedAt($sale) : null;
        if ($closedAt !== null && $until->equalTo($closedAt)) {
            // The session's close ends the window: a void recorded at or after it is refused.
            return $at->lessThan($closedAt);
        }

        return $at->lessThanOrEqualTo($until);
    }

    /**
     * Why a void at $at is refused, for the policy and the push.
     */
    public function refusal(Sale $sale, CarbonInterface $at): string
    {
        $setting = $this->settingFor($sale);

        if (is_int($setting)) {
            return __('This sale can no longer be voided (limit: :minutes minutes). Use a return instead.', ['minutes' => $setting]);
        }

        $closedAt = $setting === self::SESSION ? $this->sessionClosedAt($sale) : null;
        if ($closedAt !== null && $at->greaterThanOrEqualTo($closedAt) && $closedAt->lessThanOrEqualTo($this->until($sale) ?? $closedAt)) {
            return __('This sale\'s register session has closed, so it can\'t be voided. Use a return instead.');
        }

        return __('This sale can only be voided on the day it was made. Use a return instead.');
    }

    /**
     * Whether the sale can be voided now, for the till (stage 10, step 5c;
     * `voidable`): about time and the sale's state only, so the same for
     * everyone; the app adds the person's `sales.void`. True for a completed
     * sale inside its window, and for a pending sale with payments (no window);
     * false when voided, with a return that isn't rejected, past its window, or
     * pending with no payments (deleted instead).
     */
    public function voidable(Sale $sale): bool
    {
        $status = $sale->status instanceof \BackedEnum ? $sale->status->value : $sale->status;

        return match (true) {
            $status === 'voided', self::hasOpenReturns($sale) => false,
            $status === 'pending', $status === 'draft' => (float) $sale->paid_amount > 0 || $sale->payments()->exists(),
            default => $this->allows($sale, now()),
        };
    }

    /**
     * The end of the window while the sale is voidable and has one (`voidable_until`).
     */
    public function voidableUntil(Sale $sale): ?CarbonImmutable
    {
        return $this->voidable($sale) ? $this->until($sale) : null;
    }

    /**
     * The shop's timezone, else the business's, else Africa/Nairobi.
     */
    public function timezone(Sale $sale): string
    {
        $shop = $sale->relationLoaded('shop') ? $sale->shop : $sale->shop()->withTrashed()->first();

        return $shop?->localTimezone() ?? self::DEFAULT_TIMEZONE;
    }

    /**
     * The window that applies to the sale: the business's setting, with
     * `session` becoming `end_of_day` for a sale with no session.
     */
    public function mode(Sale $sale): string|int
    {
        return $this->settingFor($sale);
    }

    private function settingFor(Sale $sale): string|int
    {
        $shop = $sale->relationLoaded('shop') ? $sale->shop : $sale->shop()->withTrashed()->first();
        $setting = self::setting($shop?->business);

        // A sale with no session (an online order) uses the end of the day.
        return $setting === self::SESSION && $sale->register_id === null ? self::END_OF_DAY : $setting;
    }

    private function completedAt(Sale $sale): ?CarbonImmutable
    {
        $status = $sale->status instanceof \BackedEnum ? $sale->status->value : $sale->status;
        if ($status !== 'completed') {
            return null;
        }

        $at = $sale->completed_at ?? $sale->created_at;

        return $at === null ? null : CarbonImmutable::instance($at);
    }

    private function sessionClosedAt(Sale $sale): ?CarbonImmutable
    {
        $closedAt = $sale->register?->closed_at;

        return $closedAt === null ? null : CarbonImmutable::instance($closedAt);
    }
}
