<?php

namespace App\Services;

use App\Casts\Quantity;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\CostAllocation;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\Refund;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Services\Stock\StockLedger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function getAllReturns(
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?string $reason = null,
        ?int $shopId = null,
    ): LengthAwarePaginator {
        $query = SaleReturn::query()
            ->with(['sale.customer:id,name', 'customer:id,name', 'shop', 'requestedBy', 'items.product']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($reason) {
            $query->where('reason', $reason);
        }

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return $query->latest()->paginate($perPage);
    }

    public function getReturnsForSale(Sale $sale): Collection
    {
        return SaleReturn::query()
            ->where('sale_id', $sale->id)
            ->with(['items.product', 'requestedBy', 'refund'])
            ->latest()
            ->get();
    }

    public function createReturn(array $data, int $userId): SaleReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            $sale = Sale::findOrFail($data['sale_id']);
            $reason = ReturnReason::from($data['reason']);

            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'shop_id' => $sale->shop_id,
                'status' => ReturnStatus::PENDING,
                'reason' => $reason,
                'notes' => $data['notes'] ?? null,
                'restocking_fee' => $data['restocking_fee'] ?? 0,
                'requested_by' => $userId,
            ]);

            // Bulk-fetch sale items to avoid N+1 queries in loop
            $saleItemIds = collect($data['items'])->pluck('sale_item_id');
            $saleItems = SaleItem::whereIn('id', $saleItemIds)->get()->keyBy('id');

            $totalAmount = 0;

            foreach ($data['items'] as $itemData) {
                $saleItem = $saleItems->get($itemData['sale_item_id']);
                if (! $saleItem) {
                    throw new ModelNotFoundException("SaleItem [{$itemData['sale_item_id']}] not found.");
                }
                $quantity = (float) $itemData['quantity'];
                $unitPrice = $saleItem->unit_price ?? $saleItem->price ?? 0;
                $totalPrice = $unitPrice * $quantity;
                $totalAmount += $totalPrice;

                ReturnItem::create([
                    'return_id' => $saleReturn->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    ...$this->returnLineUnit($saleItem, $quantity),
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'condition' => $itemData['condition'] ?? null,
                    'condition_notes' => $itemData['condition_notes'] ?? null,
                    'is_restockable' => $reason->isRestockable(),
                    'return_to_supplier' => (bool) ($itemData['return_to_supplier'] ?? false),
                    'return_to_supplier_at' => ! empty($itemData['return_to_supplier']) ? now() : null,
                    'return_to_supplier_notes' => $itemData['return_to_supplier_notes'] ?? null,
                ]);
            }

            $restockingFee = $data['restocking_fee'] ?? 0;
            $refundAmount = $totalAmount - $restockingFee;

            $saleReturn->update([
                'total_amount' => $totalAmount,
                'refund_amount' => max(0, $refundAmount),
            ]);

            return $saleReturn->load(['sale', 'customer', 'shop', 'requestedBy', 'items.product']);
        });
    }

    public function updateReturn(SaleReturn $saleReturn, array $data): SaleReturn
    {
        if (! $saleReturn->isPending()) {
            throw new BusinessRuleException('Only pending returns can be updated.');
        }

        return DB::transaction(function () use ($saleReturn, $data) {
            $reason = isset($data['reason']) ? ReturnReason::from($data['reason']) : $saleReturn->reason;

            $saleReturn->update([
                'reason' => $reason,
                'notes' => $data['notes'] ?? $saleReturn->notes,
                'restocking_fee' => $data['restocking_fee'] ?? $saleReturn->restocking_fee,
            ]);

            if (isset($data['items'])) {
                $saleReturn->items()->delete();

                // Bulk-fetch sale items to avoid N+1 queries in loop
                $saleItemIds = collect($data['items'])->pluck('sale_item_id');
                $saleItems = SaleItem::whereIn('id', $saleItemIds)->get()->keyBy('id');

                $totalAmount = 0;

                foreach ($data['items'] as $itemData) {
                    $saleItem = $saleItems->get($itemData['sale_item_id']);
                    if (! $saleItem) {
                        throw new ModelNotFoundException("SaleItem [{$itemData['sale_item_id']}] not found.");
                    }
                    $quantity = (float) $itemData['quantity'];
                    $unitPrice = $saleItem->unit_price ?? $saleItem->price ?? 0;
                    $totalPrice = $unitPrice * $quantity;
                    $totalAmount += $totalPrice;

                    ReturnItem::create([
                        'return_id' => $saleReturn->id,
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $saleItem->product_id,
                        ...$this->returnLineUnit($saleItem, $quantity),
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'condition' => $itemData['condition'] ?? null,
                        'condition_notes' => $itemData['condition_notes'] ?? null,
                        'is_restockable' => $reason->isRestockable(),
                        'return_to_supplier' => (bool) ($itemData['return_to_supplier'] ?? false),
                        'return_to_supplier_at' => ! empty($itemData['return_to_supplier']) ? now() : null,
                        'return_to_supplier_notes' => $itemData['return_to_supplier_notes'] ?? null,
                    ]);
                }

                $restockingFee = $data['restocking_fee'] ?? $saleReturn->restocking_fee;
                $refundAmount = $totalAmount - $restockingFee;

                $saleReturn->update([
                    'total_amount' => $totalAmount,
                    'refund_amount' => max(0, $refundAmount),
                ]);
            }

            return $saleReturn->fresh()->load(['sale', 'customer', 'shop', 'requestedBy', 'items.product']);
        });
    }

    /**
     * A returned line takes the unit it was sold in (stage 11, step 3), else
     * the variation's base unit; $quantity is in that unit.
     *
     * @return array{unit_id: ?int, quantity: float, base_quantity: string}
     */
    private function returnLineUnit(SaleItem $saleItem, float $quantity): array
    {
        $variation = $saleItem->variation ?? Product::find($saleItem->product_id)?->defaultVariation()->first();
        $unit = $saleItem->unit ?? $variation?->baseUnit;

        return [
            'unit_id' => $unit?->id,
            'quantity' => $quantity,
            'base_quantity' => $this->returnBaseQuantity($saleItem, $quantity, $unit),
        ];
    }

    /**
     * The base units a returned quantity puts back: in the same proportion as the sold line
     * (its recorded base quantity over its quantity). A till may have synced a line whose base
     * figure differs from the unit's current factor, and returning it at the factor would
     * restock more than the sale removed. Falls back to the factor only for a line with no
     * recorded base quantity.
     */
    private function returnBaseQuantity(SaleItem $saleItem, float $quantity, ?ProductUnit $unit): string
    {
        $soldQuantity = Quantity::normalize($saleItem->quantity ?? 0);

        if (bccomp($soldQuantity, '0', 3) > 0 && $saleItem->base_quantity !== null) {
            return bcdiv(bcmul(Quantity::normalize($quantity), Quantity::normalize($saleItem->base_quantity), 6), $soldQuantity, 3);
        }

        return bcmul(Quantity::normalize($quantity), (string) ($unit?->factor ?? '1'), 3);
    }

    public function approveReturn(SaleReturn $saleReturn, int $userId): SaleReturn
    {
        if (! $saleReturn->canBeApproved()) {
            throw new BusinessRuleException('Return cannot be approved in its current status.');
        }

        $saleReturn->update([
            'status' => ReturnStatus::APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $saleReturn->fresh();
    }

    public function rejectReturn(SaleReturn $saleReturn, int $userId, ?string $reason = null): SaleReturn
    {
        if (! $saleReturn->canBeRejected()) {
            throw new BusinessRuleException('Return cannot be rejected in its current status.');
        }

        $saleReturn->update([
            'status' => ReturnStatus::REJECTED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $saleReturn->fresh();
    }

    public function receiveReturn(SaleReturn $saleReturn): SaleReturn
    {
        if (! $saleReturn->canBeReceived()) {
            throw new BusinessRuleException('Return must be approved before items can be received.');
        }

        return DB::transaction(function () use ($saleReturn): SaleReturn {
            $saleReturn->update([
                'status' => ReturnStatus::RECEIVED,
                'received_at' => now(),
            ]);

            // Restockable items go back into the shop's stock as a FIFO layer
            // at the cost they were sold at (stage 1b, agreed design).
            foreach ($saleReturn->items()->with('saleItem')->get() as $item) {
                if (! $item->is_restockable || ! $item->saleItem) {
                    continue;
                }

                $variation = $item->saleItem->variation_id
                    ? ProductVariation::withoutGlobalScope(ProductVariation::HIDE_DEFAULT)->findOrFail($item->saleItem->variation_id)
                    : Product::findOrFail($item->product_id)->defaultVariation()->firstOrFail();

                // base_quantity (stage 11, step 3): the line's own quantity may be in a
                // pack unit; restocking always moves base units, at the per-base-unit cost
                // the original sale line was costed at (its own base_quantity, not this
                // return's, since only part of the line may be coming back).
                $baseQuantity = Quantity::normalize($item->base_quantity);
                $saleItemBaseQuantity = Quantity::normalize($item->saleItem->base_quantity);
                $costPerBaseUnit = bccomp($saleItemBaseQuantity, '0', 3) > 0
                    ? round((float) $item->saleItem->total_cost / (float) $saleItemBaseQuantity, 4)
                    : (float) $item->saleItem->unit_cost;

                // Each piece goes back as the batch it was sold from, with that batch's expiry (review follow-up): an
                // expired batch returns still expired, so it is never offered for sale again as if it were fresh.
                foreach ($this->restockPieces($item->saleItem, $baseQuantity, "Quarantined return {$saleReturn->return_number}") as $piece) {
                    app(StockLedger::class)->receive((int) $saleReturn->shop_id, $variation, $piece['quantity'], $costPerBaseUnit, StockMovementType::RETURN, [
                        'source_type' => CostLayer::SOURCE_SALE_RETURN,
                        'source_id' => $saleReturn->id,
                        'reference' => $saleReturn,
                        'notes' => "Customer return {$saleReturn->return_number}.",
                        'batch_number' => $piece['batch_number'],
                        'expiry_date' => $piece['expiry_date'],
                    ]);
                }
            }

            return $saleReturn->fresh();
        });
    }

    /**
     * The batches a returned base quantity goes back into. A sale line taken from a single known batch
     * goes back to that batch with its expiry, exactly. A line taken from several batches cannot say which
     * of them the returned item came from, so it goes back as one piece at the earliest expiry among them
     * (never a later one than the item could have had). A line with an unknown batch (a provisional or
     * missing allocation, or a sale before batches were recorded) is quarantined: it goes back already
     * expired, so it is not sold until someone re-receives it after inspection.
     *
     * @return list<array{quantity: string, batch_number: ?string, expiry_date: ?string}>
     */
    private function restockPieces(SaleItem $saleItem, string $baseQuantity, string $quarantineBatch): array
    {
        $allocations = CostAllocation::query()
            ->where('consumer_type', CostAllocation::CONSUMER_SALE_ITEM)->where('sale_item_id', $saleItem->id)
            ->orderBy('id')->get();

        $layers = CostLayer::query()->whereIn('id', $allocations->pluck('cost_layer_id')->filter())->get()->keyBy('id');
        $unknown = $allocations->isEmpty() || $allocations->contains(fn (CostAllocation $allocation): bool => $allocation->cost_layer_id === null || ! $layers->has($allocation->cost_layer_id));

        if ($unknown) {
            return [['quantity' => $baseQuantity, 'batch_number' => $quarantineBatch, 'expiry_date' => now()->subDay()->toDateString()]];
        }

        $batches = $allocations->map(fn (CostAllocation $allocation): CostLayer => $layers->get($allocation->cost_layer_id));

        if ($batches->unique('id')->count() === 1) {
            $layer = $batches->first();

            return [['quantity' => $baseQuantity, 'batch_number' => $layer->batch_number, 'expiry_date' => $layer->expiry_date?->toDateString()]];
        }

        // Several batches: the earliest expiry wins, and a batch with no expiry counts as the latest.
        $earliest = $batches->sortBy(fn (CostLayer $layer): string => $layer->expiry_date?->toDateString() ?? '9999-12-31')->first();

        return [['quantity' => $baseQuantity, 'batch_number' => null, 'expiry_date' => $earliest->expiry_date?->toDateString()]];
    }

    public function inspectReturn(SaleReturn $saleReturn, int $userId, ?string $inspectionNotes = null): SaleReturn
    {
        if (! $saleReturn->canBeInspected()) {
            throw new BusinessRuleException('Return must be received before inspection.');
        }

        $saleReturn->update([
            'status' => ReturnStatus::INSPECTED,
            'inspected_at' => now(),
            'inspected_by' => $userId,
            'inspection_notes' => $inspectionNotes,
        ]);

        return $saleReturn->fresh();
    }

    public function createRefund(SaleReturn $saleReturn, array $data, int $userId): Refund
    {
        if (! $saleReturn->canBeRefunded()) {
            throw new BusinessRuleException('Return is not eligible for a refund in its current status.');
        }

        return DB::transaction(function () use ($saleReturn, $data, $userId) {
            $refund = Refund::create([
                'return_id' => $saleReturn->id,
                'customer_id' => $saleReturn->customer_id,
                'shop_id' => $saleReturn->shop_id,
                'method' => $data['method'],
                'amount' => $data['amount'],
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'processed_by' => $userId,
            ]);

            $saleReturn->update(['status' => ReturnStatus::COMPLETED]);

            return $refund->load(['saleReturn', 'customer', 'shop', 'processedBy']);
        });
    }

    public function processRefund(Refund $refund, int $userId, ?string $transactionId = null): Refund
    {
        if (! $refund->isPending()) {
            throw new BusinessRuleException('Refund has already been processed.');
        }

        $refund->update([
            'status' => 'completed',
            'processed_by' => $userId,
            'processed_at' => now(),
            'transaction_id' => $transactionId,
        ]);

        return $refund->fresh();
    }

    public function deleteReturn(SaleReturn $saleReturn): bool
    {
        if (! $saleReturn->isPending()) {
            throw new BusinessRuleException('Only pending returns can be deleted.');
        }

        return DB::transaction(function () use ($saleReturn) {
            $saleReturn->items()->delete();

            return $saleReturn->delete();
        });
    }

    /**
     * @return array<string, int>
     */
    public function getStatistics(?int $shopId = null): array
    {
        $query = SaleReturn::query();

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        $stats = (clone $query)->selectRaw("
            COUNT(*) as total_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
        ")->first();

        return [
            'total_count' => (int) $stats->total_count,
            'pending_count' => (int) $stats->pending_count,
            'approved_count' => (int) $stats->approved_count,
            'completed_count' => (int) $stats->completed_count,
            'rejected_count' => (int) $stats->rejected_count,
        ];
    }
}
