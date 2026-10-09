<?php

namespace App\Actions\StockIntake;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockIntakeStatus;
use App\Models\StockIntake;
use Illuminate\Support\Facades\DB;

class CompleteStockIntakeAction
{
    public function execute(StockIntake $stockIntake, ?int $userId = null): StockIntake
    {
        if (! $stockIntake->canComplete()) {
            throw new \Exception('Stock intake cannot be completed in current status.');
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

            // Update product stock quantity
            $product = $stockIntake->product;
            if ($product) {
                $product->increment('stock_quantity', $stockIntake->quantity_accepted);
            }

            return $stockIntake->fresh();
        });
    }

    /**
     * Update the purchase order status based on items receiving progress.
     */
    private function updatePurchaseOrderStatus(\App\Models\PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->refresh();

        $allItems = $purchaseOrder->items;
        $allFullyReceived = $allItems->every(fn($item) => $item->isFullyReceived());
        $anyReceived = $allItems->contains(fn($item) => $item->quantity_received > 0);

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
