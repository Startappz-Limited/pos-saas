<?php

namespace App\Http\Requests;

use App\Models\PurchaseReturn;

class UpdatePurchaseReturnRequest extends StorePurchaseReturnRequest
{
    public function authorize(): bool
    {
        $purchaseReturn = $this->route('purchase_return');

        return $purchaseReturn instanceof PurchaseReturn
            && $this->user()->can('update', $purchaseReturn);
    }

    protected function currentPurchaseReturnId(): ?int
    {
        $purchaseReturn = $this->route('purchase_return');

        return $purchaseReturn instanceof PurchaseReturn ? $purchaseReturn->id : null;
    }
}
