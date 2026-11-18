<?php

namespace App\Actions\StockIntake;

use App\Casts\Quantity;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockIntakeStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\CostLayer;
use App\Models\ProductVariation;
use App\Models\PurchaseOrder;
use App\Models\StockIntake;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;

class CompleteStockIntakeAction
{
    public function execute(StockIntake $stockIntake, ?int $userId = null): StockIntake
    {
        if (! $stockIntake->canComplete()) {
            throw new BusinessRuleException('Stock intake cannot be completed in current status.');
        }

        return DB::transaction(function () use ($stockIntake, $userId) {
            // Update stock intake status
            $stockIntake->update([
                'status' => StockIntakeStatus::COMPLETED,
                'completed_by' => $userId,
                'completed_at' => now(),
                'updated_by' => $userId,
            ]);

            // Update purchase order item quantities
            $purchaseOrderItem = $stockIntake->purchaseOrderItem;
            if ($purchaseOrderItem) {
                $purchaseOrderItem->recordReceivedQuantity($stockIntake->quantity_accepted);

                // Auto-update purchase order status based on receiving progress
                $this->updatePurchaseOrderStatus($purchaseOrderItem->purchaseOrder);
            }

            // The accepted units join the intake's shop as a FIFO cost layer, at
            // the purchase order line's cost when there is one (stage 1b).
            $product = $stockIntake->product;
            // The accepted quantity at its three decimals (a 2.5 kg delivery adds 2.5, not 2).
            $quantity = Quantity::normalize($stockIntake->quantity_accepted);
            if ($product && bccomp($quantity, '0', 3) > 0) {
                $variation = $stockIntake->product_variation_id
                    ? ProductVariation::withoutGlobalScope(ProductVariation::HIDE_DEFAULT)->findOrFail($stockIntake->product_variation_id)
                    : $product->defaultVariation()->firstOrFail();
                $ledger = app(StockLedger::class);
                $unitCost = $purchaseOrderItem?->unit_cost !== null
                    ? (float) $purchaseOrderItem->unit_cost
                    : $ledger->fallbackCost((int) $stockIntake->shop_id, $variation);

                $ledger->receive((int) $stockIntake->shop_id, $variation, $quantity, $unitCost, StockMovementType::INTAKE, [
                    'source_type' => CostLayer::SOURCE_INTAKE,
                    'source_id' => $stockIntake->id,
                    'stock_intake_id' => $stockIntake->id,
                    'reference' => $stockIntake,
                    'notes' => "Stock intake {$stockIntake->intake_number}.",
                    'user_id' => $userId,
                    // The batch this stock belongs to (stage 11, step 5); the request already
                    // required both when the variation tracks batches.
                    'batch_number' => $stockIntake->batch_number,
                    'expiry_date' => $stockIntake->expiry_date,
                ]);
            }

            return $stockIntake->fresh();
        });
    }

    /**
     * Update the purchase order status based on items receiving progress.
     */
    private function updatePurchaseOrderStatus(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->refresh();

        $allItems = $purchaseOrder->items;
        $allFullyReceived = $allItems->every(fn ($item) => $item->isFullyReceived());
        $anyReceived = $allItems->contains(fn ($item) => $item->quantity_received > 0);

        if ($allFullyReceived) {
            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::RECEIVED,
                'actual_delivery_date' => now()->toDateString(),
            ]);
        } elseif ($anyReceived && $purchaseOrder->status->canReceive()) {
            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::PARTIALLY_RECEIVED,
            ]);
        }
    }
}
