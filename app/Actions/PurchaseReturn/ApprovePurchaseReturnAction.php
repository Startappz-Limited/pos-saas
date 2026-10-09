<?php

namespace App\Actions\PurchaseReturn;

use App\Enums\PurchaseReturnStatus;
use App\Models\PurchaseReturn;

class ApprovePurchaseReturnAction
{
    public function execute(PurchaseReturn $purchaseReturn, int $userId): PurchaseReturn
    {
        if (! $purchaseReturn->canApprove()) {
            throw new \Exception('Supplier return cannot be approved in its current status.');
        }

        $purchaseReturn->update([
            'status' => PurchaseReturnStatus::APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $purchaseReturn->fresh();
    }
}
