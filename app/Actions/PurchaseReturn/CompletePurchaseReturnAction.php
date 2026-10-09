<?php

namespace App\Actions\PurchaseReturn;

use App\Enums\PurchaseReturnStatus;
use App\Models\PurchaseReturn;

class CompletePurchaseReturnAction
{
    public function execute(PurchaseReturn $purchaseReturn, array $data, int $userId): PurchaseReturn
    {
        if (! $purchaseReturn->canComplete()) {
            throw new \Exception('Supplier return must be returned to supplier before it can be completed.');
        }

        $purchaseReturn->update([
            'status' => PurchaseReturnStatus::COMPLETED,
            'supplier_credit_amount' => $data['supplier_credit_amount'] ?? $purchaseReturn->supplier_credit_amount,
            'supplier_credit_reference' => $data['supplier_credit_reference'] ?? $purchaseReturn->supplier_credit_reference,
            'completed_by' => $userId,
            'completed_at' => now(),
        ]);

        return $purchaseReturn->fresh();
    }
}
