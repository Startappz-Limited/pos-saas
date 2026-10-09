<?php

namespace App\Actions\PurchaseOrder;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;

class ApprovePurchaseOrderAction
{
    public function execute(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->canApprove()) {
            throw new \Exception('Purchase order cannot be approved in current status.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrderStatus::APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'updated_by' => $userId,
        ]);

        return $purchaseOrder->fresh();
    }
}
