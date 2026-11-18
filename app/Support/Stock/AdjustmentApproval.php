<?php

namespace App\Support\Stock;

use App\Casts\Quantity;
use App\Enums\AdjustmentReason;
use App\Models\Business;
use App\Models\ProductVariation;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\Approvals\ApprovalSubject;
use App\Support\Approvals\Approvers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Who applies a stock adjustment (stage 10, step 1g; revised 3 November 2026).
 *
 * Its creator applies it on saving only when they hold
 * `stock-adjustments.approve` and it needs no second person. It needs one when:
 * - its reason is loss, theft, damage, expiry, a quality issue or a
 *   promotional giveaway (any direction);
 * - it is a recount with any line that lowers stock;
 * - any line's cost is unknown;
 * - its cost value, added to what the same person auto-applied at the same
 *   shop on the same shop-local day, is at or above the business's threshold.
 *
 * So, by reason: found, a recount that only raises stock, and "other" in
 * either direction apply on saving (within the threshold, known cost); lost,
 * stolen, damaged, expired, quality issue, promotional giveaway and a recount
 * that lowers stock always wait (decided 3 November 2026). "Returned to supplier" is no longer
 * offered (purchase returns).
 *
 * The cost value is Σ |quantity| × unit cost over the lines (gross, never
 * netted), a decrease at the cost the ledger would consume now, an increase
 * only at the cost entered on the line (stage 11, step 5) — never the
 * shop's latest batch cost silently, else unknown.
 */
final class AdjustmentApproval
{
    public const DEFAULT_THRESHOLD = 5000.0;

    /**
     * The permissions that make someone an approver.
     */
    public const PERMISSIONS = ['stock-adjustments.approve', 'stock-adjustments.full-access'];

    public function __construct(private StockLedger $ledger, private Approvers $approvers) {}

    public static function threshold(?Business $business): float
    {
        $value = data_get($business?->settings, 'stock.adjustment_approval_threshold');

        return is_numeric($value) ? (float) $value : self::DEFAULT_THRESHOLD;
    }

    /**
     * Whether $creator applies the adjustment on saving.
     */
    public function appliesOnSaving(StockAdjustment $adjustment, User $creator): bool
    {
        return $this->approvers->holds($creator, self::PERMISSIONS)
            && $creator->canAccessShop((int) $adjustment->shop_id)
            && ! $this->needsSecondPerson($adjustment);
    }

    public function needsSecondPerson(StockAdjustment $adjustment): bool
    {
        if ($adjustment->reason?->needsSecondPerson()) {
            return true;
        }

        if ($adjustment->reason === AdjustmentReason::RECOUNT && $adjustment->items->contains(fn (StockAdjustmentItem $item): bool => (float) $item->quantity_change < 0)) {
            return true;
        }

        $value = 0.0;
        foreach ($adjustment->items as $item) {
            $cost = $this->unitCost($item);
            if ($cost === null) {
                return true;
            }
            $value += abs((float) $item->quantity_change) * $cost;
        }

        return round($value + $this->autoAppliedToday($adjustment), 2) >= self::threshold($adjustment->shop?->business);
    }

    /**
     * What the adjustment's creator auto-applied at its shop on its shop-local
     * day, other than it: they count together against the threshold, so
     * splitting one large correction doesn't avoid a second person.
     */
    public function autoAppliedToday(StockAdjustment $adjustment): float
    {
        $timezone = $adjustment->shop?->localTimezone() ?? 'Africa/Nairobi';
        $start = CarbonImmutable::now($timezone)->startOfDay();

        return (float) StockAdjustment::query()
            ->where('shop_id', $adjustment->shop_id)
            ->where('created_by', $adjustment->created_by)
            ->where('auto_applied', true)
            ->when($adjustment->exists, fn ($query) => $query->whereKeyNot($adjustment->id))
            ->whereBetween('approved_at', [$start->setTimezone(config('app.timezone')), $start->endOfDay()->setTimezone(config('app.timezone'))])
            ->sum('cost_value');
    }

    /**
     * The adjustment's cost value; unknown costs count as zero.
     */
    public function value(StockAdjustment $adjustment): float
    {
        return round($adjustment->items->sum(fn (StockAdjustmentItem $item): float => abs((float) $item->quantity_change) * (float) $this->unitCost($item)), 2);
    }

    /**
     * The unit cost a line moves stock at; null when unknown. A decrease
     * costs at what the ledger would consume it from now; an increase costs
     * only at what was entered (stage 11, step 5) — never the shop's latest
     * batch cost silently, so this agrees with what apply() actually moves
     * stock at.
     */
    public function unitCost(StockAdjustmentItem $item): ?float
    {
        if ((float) $item->quantity_change < 0) {
            return $this->ledger->costToConsume((int) $item->shop_id, $this->variationOf($item), Quantity::normalize($item->quantity_change), $item->cost_layer_id, $item->cost_layer_id !== null, $item->cost_layer_id !== null);
        }

        return $item->unit_cost !== null ? (float) $item->unit_cost : null;
    }

    public function variationOf(StockAdjustmentItem $item): ProductVariation
    {
        return $item->variation ?? $item->product->defaultVariation()->firstOrFail();
    }

    /**
     * Who may approve it: eligible approvers other than its creator.
     *
     * @return Collection<int, User>
     */
    public function approversOf(StockAdjustment $adjustment): Collection
    {
        return $this->approvers->eligible(self::PERMISSIONS, (int) $adjustment->shop_id, $adjustment->creator);
    }

    public function mayApproveOwn(User $user, StockAdjustment $adjustment): bool
    {
        return $this->approvers->mayApproveOwn($user, self::PERMISSIONS, (int) $adjustment->shop_id);
    }

    public function subject(StockAdjustment $adjustment): ApprovalSubject
    {
        $adjustment->loadMissing(['shop', 'creator']);

        return new ApprovalSubject(
            kind: 'stock_adjustment',
            uuid: (string) $adjustment->uuid,
            businessId: (int) $adjustment->shop?->business_id,
            title: __('Stock adjustment :number', ['number' => $adjustment->adjustment_number]),
            summary: __(':reason at :shop, :lines line(s).', [
                'reason' => __($adjustment->reason?->label() ?? ''),
                'shop' => $adjustment->shop?->name,
                'lines' => $adjustment->items()->count(),
            ]),
            url: route('stock-adjustments.show', $adjustment),
            requestedBy: (string) $adjustment->creator?->name,
        );
    }
}
