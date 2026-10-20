<?php

namespace App\Services\Billing;

use App\Exceptions\InsufficientAiCredit;
use App\Models\AiCreditBalance;
use App\Models\AiUsageEvent;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * The prepaid AI credit of one business (Phase 4, decision 4.6).
 *
 * Every change is a single conditional UPDATE, never a read-then-write, so
 * concurrent calls can't take the balance below zero: a call reserves its
 * estimate with `WHERE balance >= estimate` and is refused when no row
 * matched; settling returns the unused part or takes the small excess (never
 * below zero); a failed call is released in full.
 */
class AiCredits
{
    public function __construct(private TenantContext $tenant) {}

    public function balance(int $businessId): float
    {
        return $this->tenant->run($businessId, fn (): float => (float) (AiCreditBalance::query()->where('business_id', $businessId)->value('balance') ?? 0));
    }

    /**
     * Credits bought by $kes, at the configured conversion.
     */
    public function creditsFor(float $kes): float
    {
        return round($kes * (float) config('ai.credits.per_kes', 10), 4);
    }

    /**
     * Adds credit (a confirmed top-up).
     */
    public function topUp(int $businessId, float $credits, ?int $userId = null): void
    {
        $this->tenant->run($businessId, fn () => DB::transaction(function () use ($businessId, $credits, $userId): void {
            AiCreditBalance::query()->firstOrCreate(['business_id' => $businessId], ['balance' => 0]);

            AiCreditBalance::query()->where('business_id', $businessId)->increment('balance', $credits, ['exhausted_notified_at' => null]);

            AiUsageEvent::query()->create([
                'business_id' => $businessId, 'provider' => 'platform', 'credits' => $credits,
                'kind' => 'topup', 'status' => 'settled', 'user_id' => $userId,
            ]);
        }));
    }

    /**
     * Takes $estimate from the balance, or refuses.
     *
     * @throws InsufficientAiCredit
     */
    public function reserve(int $businessId, float $estimate): void
    {
        $taken = $this->tenant->run($businessId, fn (): int => AiCreditBalance::query()
            ->where('business_id', $businessId)
            ->where('balance', '>=', $estimate)
            ->decrement('balance', $estimate));

        if ($taken === 0) {
            throw new InsufficientAiCredit($estimate, $this->balance($businessId));
        }
    }

    /**
     * Returns a reservation that was not used.
     */
    public function release(int $businessId, float $reserved): void
    {
        $this->tenant->run($businessId, fn () => AiCreditBalance::query()->where('business_id', $businessId)->increment('balance', $reserved));
    }

    /**
     * Settles a reservation against what the call really cost. Returns the
     * credits finally charged (an excess is taken only as far as the balance
     * reaches).
     */
    public function settle(int $businessId, float $reserved, float $actual): float
    {
        $difference = round($reserved - $actual, 4);

        if ($difference > 0) {
            $this->release($businessId, $difference);

            return $actual;
        }

        if ($difference < 0) {
            $excess = -$difference;
            $balance = $this->balance($businessId);
            $take = min($excess, $balance);

            if ($take > 0) {
                $this->tenant->run($businessId, fn () => AiCreditBalance::query()->where('business_id', $businessId)->where('balance', '>=', $take)->decrement('balance', $take));
            }

            return $reserved + $take;
        }

        return $reserved;
    }
}
