<?php

namespace App\Actions\Supplier;

use App\Models\Supplier;

class DeleteSupplierAction
{
    public function execute(Supplier $supplier): bool
    {
        return $supplier->delete();
    }
}
