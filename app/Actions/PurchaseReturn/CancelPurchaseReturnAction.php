<?php

namespace App\Actions\PurchaseReturn;

use App\Enums\PurchaseReturnStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\PurchaseReturn;

class CancelPurchaseReturnAction
{
    public function execute(PurchaseReturn $purchaseReturn, int $userId, ?string $reason = null): PurchaseReturn
    {
        if (! $purchaseReturn->canCancel()) {
            throw new BusinessRuleException('Supplier return cannot be cancelled in its current status.');
        }

        $purchaseReturn->update([
            'status' => PurchaseReturnStatus::CANCELLED,
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $purchaseReturn->fresh();
    }
}
