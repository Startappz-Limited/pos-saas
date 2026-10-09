<?php

namespace App\Actions\StockIntake;

use App\Models\StockIntake;

class UpdateStockIntakeAction
{
    public function execute(StockIntake $stockIntake, array $data, ?int $userId = null): StockIntake
    {
        if (! $stockIntake->canEdit()) {
            throw new \Exception('Stock intake cannot be edited in current status.');
        }

        $data['updated_by'] = $userId;
        $stockIntake->update($data);

        return $stockIntake->fresh();
    }
}
