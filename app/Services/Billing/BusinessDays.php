<?php

namespace App\Services\Billing;

use Illuminate\Support\Carbon;

/**
 * The business-day calendar (assumption 12): Monday to Friday, excluding the
 * public holidays in `config('billing.holidays')`. Counts the lapse and
 * warning periods.
 */
class BusinessDays
{
    public function isBusinessDay(Carbon $date): bool
    {
        return $date->isWeekday() && ! in_array($date->toDateString(), (array) config('billing.holidays', []), true);
    }

    /**
     * The date $days business days after $from (the day itself doesn't count),
     * at the start of that day.
     */
    public function add(Carbon $from, int $days): Carbon
    {
        $date = $from->copy()->startOfDay();

        while ($days > 0) {
            $date->addDay();

            if ($this->isBusinessDay($date)) {
                $days--;
            }
        }

        return $date;
    }

    /**
     * Whole business days from $from to $to (negative when $to is earlier),
     * counting each business day after $from up to and including $to.
     */
    public function between(Carbon $from, Carbon $to): int
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        if ($to->lt($from)) {
            return -$this->between($to, $from);
        }

        $count = 0;
        for ($date = $from->copy()->addDay(); $date->lte($to); $date->addDay()) {
            if ($this->isBusinessDay($date)) {
                $count++;
            }
        }

        return $count;
    }
}
