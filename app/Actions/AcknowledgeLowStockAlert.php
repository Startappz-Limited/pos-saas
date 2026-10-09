<?php

namespace App\Actions;

use App\Enums\AlertStatus;
use App\Models\LowStockAlert;

class AcknowledgeLowStockAlert
{
    /**
     * Acknowledge a low stock alert.
     */
    public function execute(LowStockAlert $alert, int $userId, ?string $newStatus = null): LowStockAlert
    {
        $status = $newStatus ? AlertStatus::from($newStatus) : AlertStatus::ACKNOWLEDGED;

        $alert->update([
            'status' => $status,
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
            'updated_by' => $userId,
        ]);

        return $alert->fresh();
    }
}
