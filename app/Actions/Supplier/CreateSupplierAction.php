<?php

namespace App\Actions\Supplier;

use App\Models\Supplier;
use Illuminate\Support\Str;

class CreateSupplierAction
{
    public function execute(array $data): Supplier
    {
        if (empty($data['uuid'])) {
            $data['uuid'] = (string) Str::uuid();
        }

        if (empty($data['code'])) {
            $data['code'] = 'SUP-'.strtoupper(Str::random(8));
        }

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return Supplier::create($data);
    }
}
