<?php

namespace App\Services;

use App\Actions\StockIntake\CompleteStockIntakeAction;
use App\Actions\StockIntake\CreateStockIntakeAction;
use App\Actions\StockIntake\UpdateStockIntakeAction;
use App\Enums\StockIntakeStatus;
use App\Models\PurchaseOrderItem;
use App\Models\StockIntake;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class StockIntakeService
{
    public function __construct(
        protected CreateStockIntakeAction $createAction,
        protected UpdateStockIntakeAction $updateAction,
        protected CompleteStockIntakeAction $completeAction,
    ) {}

    public function getAllStockIntakes(
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?int $shopId = null,
        ?int $supplierId = null,
    ): LengthAwarePaginator {
        $query = StockIntake::query()->with(['product', 'supplier', 'shop', 'purchaseOrder']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('intake_number', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return $query->latest('intake_date')->paginate($perPage);
    }

    public function getPendingStockIntakes(): Collection
    {
        return StockIntake::pending()->with(['product', 'supplier', 'shop'])->latest()->get();
    }

    public function getCompletedStockIntakes(): Collection
    {
        return StockIntake::completed()->with(['product', 'supplier', 'shop'])->latest()->get();
    }

    public function getStockIntakesWithQualityIssues(): Collection
    {
        return StockIntake::withQualityIssues()->with(['product', 'supplier', 'shop'])->latest()->get();
    }

    public function createStockIntake(array $data, ?int $userId = null): StockIntake
    {
        return $this->createAction->execute($data, $userId);
    }

    public function createStockIntakeFromPurchaseOrderItem(PurchaseOrderItem $item, array $data, ?int $userId = null): StockIntake
    {
        $intakeData = array_merge($data, [
            'purchase_order_id' => $item->purchase_order_id,
            'purchase_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_variation_id' => $item->product_variation_id,
            'supplier_id' => $item->purchaseOrder->supplier_id,
            'shop_id' => $item->purchaseOrder->shop_id,
            'unit' => $item->unit,
        ]);

        return $this->createStockIntake($intakeData, $userId);
    }

    public function updateStockIntake(StockIntake $stockIntake, array $data, ?int $userId = null): StockIntake
    {
        return $this->updateAction->execute($stockIntake, $data, $userId);
    }

    public function completeStockIntake(StockIntake $stockIntake, ?int $userId = null): StockIntake
    {
        return $this->completeAction->execute($stockIntake, $userId);
    }

    public function changeStatus(StockIntake $stockIntake, StockIntakeStatus $status, ?int $userId = null): StockIntake
    {
        $stockIntake->update([
            'status' => $status,
            'updated_by' => $userId,
        ]);

        return $stockIntake->fresh();
    }

    public function cancelStockIntake(StockIntake $stockIntake, ?int $userId = null): StockIntake
    {
        if (! $stockIntake->canCancel()) {
            throw new \Exception('Stock intake cannot be cancelled in current status.');
        }

        return $this->changeStatus($stockIntake, StockIntakeStatus::CANCELLED, $userId);
    }

    public function getStatistics(): array
    {
        return [
            'total_count' => StockIntake::count(),
            'pending_count' => StockIntake::pending()->count(),
            'in_progress_count' => StockIntake::inProgress()->count(),
            'completed_count' => StockIntake::completed()->count(),
            'total_quantity_received' => StockIntake::sum('quantity_received'),
            'total_quantity_accepted' => StockIntake::sum('quantity_accepted'),
            'total_quantity_rejected' => StockIntake::sum('quantity_rejected'),
            'quality_issues_count' => StockIntake::withQualityIssues()->count(),
        ];
    }
}
