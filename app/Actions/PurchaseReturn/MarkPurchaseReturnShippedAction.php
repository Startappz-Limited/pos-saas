<?php

namespace App\Actions\PurchaseReturn;

use App\Actions\RecordStockMovement;
use App\Enums\PurchaseReturnStatus;
use App\Enums\StockMovementType;
use App\Models\PurchaseReturn;
use Illuminate\Support\Facades\DB;

class MarkPurchaseReturnShippedAction
{
    public function __construct(private RecordStockMovement $recordStockMovement) {}

    public function execute(PurchaseReturn $purchaseReturn, int $userId, ?string $shipmentReference = null): PurchaseReturn
    {
        if (! $purchaseReturn->canShip()) {
            throw new \Exception('Supplier return must be approved before it can be marked as returned to supplier.');
        }

        return DB::transaction(function () use ($purchaseReturn, $userId, $shipmentReference): PurchaseReturn {
            $purchaseReturn->load(['items.product', 'items.productVariation']);

            foreach ($purchaseReturn->items as $item) {
                $currentStock = $item->productVariation
                    ? (int) $item->productVariation->stock_quantity
                    : (int) $item->product->stock_quantity;

                if ($currentStock < $item->quantity) {
                    throw new \Exception("Cannot return {$item->product->name}: only {$currentStock} units are available in stock.");
                }

                $this->recordStockMovement->execute([
                    'shop_id' => $purchaseReturn->shop_id,
                    'product_id' => $item->product_id,
                    'variation_id' => $item->product_variation_id,
                    'movement_type' => StockMovementType::SUPPLIER_RETURN->value,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'reference_type' => PurchaseReturn::class,
                    'reference_uuid' => $purchaseReturn->uuid,
                    'notes' => "Returned to supplier via {$purchaseReturn->return_number}.",
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $purchaseReturn->update([
                'status' => PurchaseReturnStatus::SHIPPED,
                'shipment_reference' => $shipmentReference ?? $purchaseReturn->shipment_reference,
                'shipped_by' => $userId,
                'shipped_at' => now(),
            ]);

            return $purchaseReturn->fresh();
        });
    }
}
