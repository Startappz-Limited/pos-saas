<?php

namespace App\Actions\Supplier;

use App\Models\Supplier;

class UpdateSupplierAction
{
    public function execute(Supplier $supplier, array $data): Supplier
    {
        if (array_key_exists('currency', $data) && blank($data['currency'])) {
            unset($data['currency']);
        }

        $data['updated_by'] = auth()->id();

        $supplier->update($data);

        return $supplier->fresh();
    }
}
