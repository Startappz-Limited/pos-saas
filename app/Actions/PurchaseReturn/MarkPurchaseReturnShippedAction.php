<?php

namespace App\Actions\PurchaseReturn;

use App\Enums\PurchaseReturnStatus;
use App\Enums\StockMovementType;
use App\Models\ProductVariation;
use App\Models\PurchaseReturn;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;

class MarkPurchaseReturnShippedAction
{
    public function __construct(private StockLedger $ledger) {}

    public function execute(PurchaseReturn $purchaseReturn, int $userId, ?string $shipmentReference = null): PurchaseReturn
    {
        if (! $purchaseReturn->canShip()) {
            throw new \Exception('Supplier return must be approved before it can be marked as returned to supplier.');
        }

        return DB::transaction(function () use ($purchaseReturn, $userId, $shipmentReference): PurchaseReturn {
            $purchaseReturn->load(['items.product']);

            foreach ($purchaseReturn->items as $item) {
                $variation = $item->product_variation_id
                    ? ProductVariation::withoutGlobalScope(ProductVariation::HIDE_DEFAULT)->findOrFail($item->product_variation_id)
                    : $item->product->defaultVariation()->firstOrFail();
                $currentStock = $this->ledger->quantity((int) $purchaseReturn->shop_id, $variation->id);

                if ($currentStock < $item->quantity) {
                    throw new \Exception("Cannot return {$item->product->name}: only {$currentStock} units are available in stock.");
                }

                // The returned intake's own cost layer goes first (stage 1b).
                $intakeLayer = $item->stock_intake_id
                    ? DB::table('cost_layers')->where('stock_intake_id', $item->stock_intake_id)->where('variation_id', $variation->id)->value('id')
                    : null;

                $this->ledger->consume((int) $purchaseReturn->shop_id, $variation, (int) $item->quantity, StockMovementType::SUPPLIER_RETURN, $item, [
                    'reference' => $purchaseReturn,
                    'notes' => "Returned to supplier via {$purchaseReturn->return_number}.",
                    'user_id' => $userId,
                ], $intakeLayer);
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
