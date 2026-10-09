<?php

namespace App\Actions\StockIntake;

use App\Enums\StockIntakeStatus;
use App\Models\Shop;
use App\Models\StockIntake;
use Illuminate\Support\Str;

class CreateStockIntakeAction
{
    public function execute(array $data, ?int $userId = null): StockIntake
    {
        $data['status'] = $data['status'] ?? StockIntakeStatus::PENDING;
        $data['received_by'] = $data['received_by'] ?? $userId;
        $data['created_by'] = $userId;

        $data['intake_date'] = $data['intake_date'] ?? now()->toDateString();
        $data['batch_number'] = $data['batch_number'] ?? 'BATCH-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));

        if (empty($data['storage_location']) && ! empty($data['shop_id'])) {
            $shop = Shop::find($data['shop_id']);
            $data['storage_location'] = $shop?->name ?? 'Main Storage';
        }

        if (empty($data['bin_location'])) {
            $data['bin_location'] = strtoupper(Str::random(2)) . '-' . strtoupper(Str::random(2)) . '-' . strtoupper(Str::random(2));
        }

        return StockIntake::create($data);
    }
}
