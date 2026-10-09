<?php

namespace App\Actions;

use App\Enums\AlertStatus;
use App\Models\LowStockAlert;

class CreateLowStockAlert
{
    /**
     * Create a low stock alert.
     */
    public function execute(array $data): LowStockAlert
    {
        // Check if alert already exists and is active
        $existingAlert = LowStockAlert::where('shop_id', $data['shop_id'])
            ->where('product_id', $data['product_id'])
            ->where('variation_id', $data['variation_id'] ?? null)
            ->whereIn('status', [AlertStatus::PENDING, AlertStatus::ACKNOWLEDGED])
            ->first();

        if ($existingAlert) {
            // Update existing alert
            $existingAlert->update([
                'current_quantity' => $data['current_quantity'],
                'threshold_quantity' => $data['threshold_quantity'],
                'updated_by' => $data['created_by'],
            ]);

            return $existingAlert->fresh();
        }

        // Create new alert
        return LowStockAlert::create([
            'uuid' => $data['uuid'] ?? null,
            'shop_id' => $data['shop_id'],
            'product_id' => $data['product_id'],
            'variation_id' => $data['variation_id'] ?? null,
            'current_quantity' => $data['current_quantity'],
            'threshold_quantity' => $data['threshold_quantity'],
            'status' => AlertStatus::PENDING,
            'created_by' => $data['created_by'],
            'updated_by' => $data['created_by'],
        ]);
    }
}
