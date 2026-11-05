<?php

namespace App\Services;

use App\Enums\AdjustmentType;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\Approvals\ApprovalInbox;
use App\Support\Stock\AdjustmentApproval;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(private StockLedger $ledger, private AdjustmentApproval $approval, private ApprovalInbox $inbox) {}

    public function getAllAdjustments(
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?string $type = null,
        ?int $shopId = null,
        bool $selfApprovedOnly = false,
    ): LengthAwarePaginator {
        $query = StockAdjustment::query()
            ->with(['shop', 'creator', 'approver', 'items.product']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('adjustment_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        if ($selfApprovedOnly) {
            $query->where('self_approved', true);
        }

        return $query->latest()->paginate($perPage);
    }

    public function getPendingAdjustments(): Collection
    {
        return StockAdjustment::pending()
            ->with(['shop', 'creator', 'items.product'])
            ->latest()
            ->get();
    }

    /**
     * Saves an adjustment and, when it doesn't need a second person, applies it at
     * once as its creator's (stage 10, step 1g). Its lines are signed: `+` adds
     * stock, `−` removes it, each agreeing with the reason's direction (checked by
     * StoreStockAdjustmentRequest).
     *
     * @param  array{shop_id: int, reason: string, notes?: string|null, items: list<array{product_id: int, variation_id?: int|null, quantity_change: int, item_notes?: string|null}>}  $data
     */
    public function createAdjustment(array $data, ?int $userId = null): StockAdjustment
    {
        return DB::transaction(function () use ($data, $userId) {
            $adjustment = StockAdjustment::create([
                'shop_id' => $data['shop_id'],
                'type' => AdjustmentType::fromChanges(array_map(fn (array $item): int => (int) $item['quantity_change'], $data['items'])),
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'created_by' => $userId,
            ]);

            foreach ($data['items'] as $item) {
                $this->addItem($adjustment, $item);
            }

            $adjustment->load(['shop', 'creator', 'items.product', 'items.variation']);
            $creator = $adjustment->creator;

            // Applied on saving only by a creator holding the approve permission,
            // when no second person is needed (revised 3 November 2026);
            // otherwise every eligible approver but the creator is told.
            if ($creator !== null && $this->approval->appliesOnSaving($adjustment, $creator)) {
                $this->apply($adjustment, $creator->id, autoApplied: true);
            } else {
                $this->inbox->request($this->approval->subject($adjustment), $this->approval->approversOf($adjustment));
                $this->touchPendingDecreases($adjustment);
            }

            return $adjustment->fresh(['shop', 'creator', 'approver', 'items.product', 'items.variation']);
        });
    }

    /**
     * @param  array{reason?: string, notes?: string|null, items?: list<array{product_id: int, variation_id?: int|null, quantity_change: int, item_notes?: string|null}>}  $data
     */
    public function updateAdjustment(StockAdjustment $adjustment, array $data): StockAdjustment
    {
        if (! $adjustment->isPending()) {
            throw new BusinessRuleException('Only pending adjustments can be updated.');
        }

        return DB::transaction(function () use ($adjustment, $data) {
            $adjustment->update([
                'reason' => $data['reason'] ?? $adjustment->reason,
                'notes' => $data['notes'] ?? $adjustment->notes,
            ]);

            if (isset($data['items'])) {
                $this->touchPendingDecreases($adjustment);
                $adjustment->items()->delete();
                foreach ($data['items'] as $item) {
                    $this->addItem($adjustment, $item);
                }
                $adjustment->update(['type' => AdjustmentType::fromChanges(array_map(fn (array $item): int => (int) $item['quantity_change'], $data['items']))]);
                $this->touchPendingDecreases($adjustment);
            }

            return $adjustment->fresh()->load(['shop', 'creator', 'items.product', 'items.variation']);
        });
    }

    /**
     * A draft line: its variation (the product's default one for a product without
     * variations) and its signed change. Its before and after figures are written
     * when stock moves (apply()), never at draft time.
     *
     * @param  array{product_id: int, variation_id?: int|null, quantity_change: int, item_notes?: string|null}  $itemData
     */
    public function addItem(StockAdjustment $adjustment, array $itemData): StockAdjustmentItem
    {
        $product = Product::findOrFail($itemData['product_id']);
        $variationId = $itemData['variation_id'] ?? $product->defaultVariation()->firstOrFail()->id;

        return StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $product->id,
            'variation_id' => $variationId,
            'shop_id' => $adjustment->shop_id,
            'quantity_change' => (int) $itemData['quantity_change'],
            'item_notes' => $itemData['item_notes'] ?? null,
        ]);
    }

    /**
     * A second person approves, which applies the stock in the same step (stage
     * 10, step 1g). The first approval or rejection wins: the row is locked and
     * re-read. Their creator approves only as an owner nobody else could
     * relieve, with a note, flagged self-approved (the policy checks who may).
     * Also how an adjustment approved before this stage, whose stock never
     * moved, is applied.
     */
    public function approveAdjustment(StockAdjustment $adjustment, int $userId, ?string $note = null): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $userId, $note) {
            $adjustment = StockAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if (! $adjustment->canBeApproved()) {
                throw new BusinessRuleException('This adjustment has already been applied or rejected.');
            }

            $selfApproved = (int) $adjustment->created_by === $userId;
            if ($selfApproved && trim((string) $note) === '') {
                throw new BusinessRuleException('Approving your own adjustment needs a note saying why.');
            }

            $this->apply($adjustment->load(['shop', 'creator', 'items.product', 'items.variation']), $userId, selfApproved: $selfApproved, note: $note);
            $this->inbox->settle($this->approval->subject($adjustment), $adjustment->creator, 'approved', User::findOrFail($userId), $note);

            return $adjustment->fresh(['shop', 'creator', 'approver', 'items.product', 'items.variation']);
        });
    }

    /**
     * Rejecting needs a reason; the first approval or rejection wins.
     */
    public function rejectAdjustment(StockAdjustment $adjustment, int $userId, ?string $reason = null): StockAdjustment
    {
        if (trim((string) $reason) === '') {
            throw new BusinessRuleException('Rejecting an adjustment needs a reason.');
        }

        return DB::transaction(function () use ($adjustment, $userId, $reason) {
            $adjustment = StockAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if (! $adjustment->canBeRejected()) {
                throw new BusinessRuleException('This adjustment has already been applied or rejected.');
            }

            $adjustment->update([
                'status' => 'rejected',
                'approved_by' => $userId,
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);
            $this->touchPendingDecreases($adjustment);
            $this->inbox->settle($this->approval->subject($adjustment), $adjustment->creator, 'rejected', User::findOrFail($userId), $reason);

            return $adjustment->fresh();
        });
    }

    /**
     * Withdraws a pending adjustment. It keeps its number (status `cancelled`),
     * so the series has no gaps; nothing moves.
     */
    public function cancelAdjustment(StockAdjustment $adjustment, int $userId): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $userId) {
            $adjustment = StockAdjustment::query()->whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if (! $adjustment->isPending()) {
                throw new BusinessRuleException('Only pending adjustments can be cancelled.');
            }

            $adjustment->update(['status' => 'cancelled', 'cancelled_by' => $userId, 'cancelled_at' => now()]);
            $this->touchPendingDecreases($adjustment);
            $this->inbox->resolve('stock_adjustment', (string) $adjustment->uuid, 'cancelled');

            return $adjustment->fresh();
        });
    }

    /**
     * Moves the stock of every line, by variation, through the ledger, writes each
     * line's before and after figures from the change, and marks the adjustment
     * applied (`completed`). An increase is received at the shop's latest batch
     * cost of the variation, else at an unknown cost (stage 10, step 1g); a
     * decrease consumes batches oldest first.
     */
    private function apply(StockAdjustment $adjustment, int $userId, bool $autoApplied = false, bool $selfApproved = false, ?string $note = null): void
    {
        // Valued before stock moves, at the costs the lines move at.
        $costValue = $this->approval->value($adjustment);

        $shopId = (int) $adjustment->shop_id;
        $context = ['reference' => $adjustment, 'notes' => "Stock adjustment {$adjustment->adjustment_number}.", 'user_id' => $userId];

        foreach ($adjustment->items as $item) {
            $variation = $this->approval->variationOf($item);
            $quantity = (int) $item->quantity_change;

            if ($quantity > 0) {
                $change = $this->ledger->receive($shopId, $variation, $quantity, $this->ledger->latestBatchCost($shopId, $variation), StockMovementType::ADJUSTMENT_ADD, [
                    ...$context, 'source_type' => CostLayer::SOURCE_ADJUSTMENT, 'source_id' => $adjustment->id,
                ]);
            } else {
                if ($this->ledger->quantity($shopId, $variation->id) + $quantity < 0) {
                    throw new BusinessRuleException("Cannot apply this adjustment: it would take {$item->product->name} below zero.");
                }
                $change = $this->ledger->consume($shopId, $variation, -$quantity, StockMovementType::ADJUSTMENT_REMOVE, $item, $context);
            }

            $item->update([
                'quantity_before' => $change->movement->quantity_before,
                'quantity_after' => $change->movement->quantity_after,
            ]);
        }

        $adjustment->update([
            'status' => 'completed',
            'approved_by' => $userId,
            'approved_at' => now(),
            'auto_applied' => $autoApplied,
            'self_approved' => $selfApproved,
            'approval_note' => $note,
            'cost_value' => $costValue,
        ]);
    }

    /**
     * Tills show units pending decreases would remove (stage 10, step 5c): their
     * stock rows are moved on when a pending adjustment's decreases change.
     */
    private function touchPendingDecreases(StockAdjustment $adjustment): void
    {
        foreach ($adjustment->items()->where('quantity_change', '<', 0)->whereNotNull('variation_id')->get(['shop_id', 'variation_id']) as $item) {
            $this->ledger->touch((int) $item->shop_id, (int) $item->variation_id);
        }
    }

    public function getStatistics(?int $shopId = null): array
    {
        $query = StockAdjustment::query();

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return [
            'total_count' => (clone $query)->count(),
            'pending_count' => (clone $query)->pending()->count(),
            'approved_count' => (clone $query)->approved()->count(),
            'completed_count' => (clone $query)->completed()->count(),
            'rejected_count' => (clone $query)->rejected()->count(),
            'cancelled_count' => (clone $query)->where('status', 'cancelled')->count(),
            'increase_count' => (clone $query)->where('type', AdjustmentType::INCREASE)->count(),
            'mixed_count' => (clone $query)->where('type', AdjustmentType::MIXED)->count(),
            'decrease_count' => (clone $query)->where('type', AdjustmentType::DECREASE)->count(),
        ];
    }
}
