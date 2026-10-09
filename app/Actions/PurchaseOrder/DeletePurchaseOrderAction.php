<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrder;

class DeletePurchaseOrderAction
{
    public function execute(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->delete();
    }
}
