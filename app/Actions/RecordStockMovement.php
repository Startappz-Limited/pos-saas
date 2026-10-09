<?php

namespace App\Actions;

use App\Enums\StockMovementType;
use App\Models\StockMovement;

class RecordStockMovement
{
    /**
     * Record a stock movement transaction.
     */
    public function execute(array $data): StockMovement
    {
        // Get current stock quantity
        $currentQuantity = $this->getCurrentQuantity($data);

        // Calculate new quantity
        $movementType = StockMovementType::from($data['movement_type']);
        $quantityChange = $data['quantity'];

        $newQuantity = $movementType->isAddition()
            ? $currentQuantity + $quantityChange
            : $currentQuantity - $quantityChange;

        // Create movement record
        $movement = StockMovement::create([
            'uuid' => $data['uuid'] ?? null,
            'shop_id' => $data['shop_id'],
            'product_id' => $data['product_id'],
            'variation_id' => $data['variation_id'] ?? null,
            'stock_batch_id' => $data['stock_batch_id'] ?? null,
            'movement_type' => $data['movement_type'],
            'quantity' => $quantityChange,
            'quantity_before' => $currentQuantity,
            'quantity_after' => $newQuantity,
            'unit_cost' => $data['unit_cost'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_uuid' => $data['reference_uuid'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'],
            'updated_by' => $data['updated_by'] ?? $data['created_by'],
        ]);

        // Update product/variation stock quantity
        $this->updateStockQuantity($data, $newQuantity);

        return $movement->fresh();
    }

    /**
     * Get current stock quantity.
     */
    protected function getCurrentQuantity(array $data): int
    {
        if (isset($data['variation_id']) && $data['variation_id']) {
            $variation = \App\Models\ProductVariation::find($data['variation_id']);

            return $variation?->stock_quantity ?? 0;
        }

        $product = \App\Models\Product::find($data['product_id']);

        return $product?->stock_quantity ?? 0;
    }

    /**
     * Update stock quantity on product or variation.
     */
    protected function updateStockQuantity(array $data, int $newQuantity): void
    {
        if (isset($data['variation_id']) && $data['variation_id']) {
            \App\Models\ProductVariation::where('id', $data['variation_id'])
                ->update(['stock_quantity' => $newQuantity]);
        } else {
            \App\Models\Product::where('id', $data['product_id'])
                ->update(['stock_quantity' => $newQuantity]);
        }
    }
}
