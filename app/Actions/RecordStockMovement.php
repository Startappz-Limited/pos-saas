<?php

namespace App\Actions;

use App\Enums\StockMovementType;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\StockMovement;
use App\Services\Stock\StockLedger;

/**
 * Records a manual stock movement (stage 1b): the change goes through the
 * shared stock service, so the shop's stock moves by a delta under a row lock
 * (no lost updates) and FIFO layers follow. Additions become a layer at the
 * given cost (else the fallback cost); deductions consume layers oldest first.
 */
class RecordStockMovement
{
    public function __construct(private StockLedger $ledger) {}

    public function execute(array $data): StockMovement
    {
        $type = StockMovementType::from($data['movement_type']);
        $variation = ! empty($data['variation_id'])
            ? ProductVariation::withoutGlobalScope(ProductVariation::HIDE_DEFAULT)->findOrFail($data['variation_id'])
            : Product::findOrFail($data['product_id'])->defaultVariation()->firstOrFail();
        $shopId = (int) $data['shop_id'];
        $context = [
            'notes' => $data['notes'] ?? null,
            'user_id' => $data['created_by'] ?? null,
        ];

        if ($type->isAddition()) {
            $unitCost = isset($data['unit_cost']) ? (float) $data['unit_cost'] : $this->ledger->fallbackCost($shopId, $variation);
            $change = $this->ledger->receive($shopId, $variation, (int) $data['quantity'], $unitCost, $type, [...$context, 'source_type' => CostLayer::SOURCE_ADJUSTMENT]);
        } else {
            $change = $this->ledger->consume($shopId, $variation, (int) $data['quantity'], $type, $variation, $context);
        }

        // The caller's own reference (e.g. a supplier return document).
        if (! empty($data['reference_type']) || ! empty($data['reference_uuid'])) {
            $change->movement->update([
                'reference_type' => $data['reference_type'] ?? null,
                'reference_uuid' => $data['reference_uuid'] ?? null,
            ]);
        }

        return $change->movement->fresh();
    }
}
