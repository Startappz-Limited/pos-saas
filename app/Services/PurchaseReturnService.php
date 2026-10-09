<?php

namespace App\Services;

use App\Actions\PurchaseReturn\ApprovePurchaseReturnAction;
use App\Actions\PurchaseReturn\CancelPurchaseReturnAction;
use App\Actions\PurchaseReturn\CompletePurchaseReturnAction;
use App\Actions\PurchaseReturn\CreatePurchaseReturnAction;
use App\Actions\PurchaseReturn\MarkPurchaseReturnShippedAction;
use App\Actions\PurchaseReturn\UpdatePurchaseReturnAction;
use App\Enums\PurchaseReturnStatus;
use App\Models\PurchaseReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PurchaseReturnService
{
    public function __construct(
        private CreatePurchaseReturnAction $createAction,
        private UpdatePurchaseReturnAction $updateAction,
        private ApprovePurchaseReturnAction $approveAction,
        private MarkPurchaseReturnShippedAction $shipAction,
        private CompletePurchaseReturnAction $completeAction,
        private CancelPurchaseReturnAction $cancelAction,
    ) {}

    public function getAllReturns(
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?string $reason = null,
        ?int $supplierId = null,
        ?int $shopId = null,
    ): LengthAwarePaginator {
        $query = PurchaseReturn::query()
            ->with(['supplier', 'shop', 'purchaseOrder', 'saleReturn', 'requestedBy', 'items.product']);

        if ($search) {
            $query->where(function ($query) use ($search): void {
                $query->where('return_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('supplier_credit_reference', 'like', "%{$search}%")
                    ->orWhere('shipment_reference', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($reason) {
            $query->where('reason', $reason);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return $query->latest()->paginate($perPage);
    }

    public function createReturn(array $data, ?int $userId = null): PurchaseReturn
    {
        return $this->createAction->execute($data, $userId);
    }

    public function updateReturn(PurchaseReturn $purchaseReturn, array $data, ?int $userId = null): PurchaseReturn
    {
        return $this->updateAction->execute($purchaseReturn, $data, $userId);
    }

    public function approveReturn(PurchaseReturn $purchaseReturn, int $userId): PurchaseReturn
    {
        return $this->approveAction->execute($purchaseReturn, $userId);
    }

    public function markAsShipped(PurchaseReturn $purchaseReturn, int $userId, ?string $shipmentReference = null): PurchaseReturn
    {
        return $this->shipAction->execute($purchaseReturn, $userId, $shipmentReference);
    }

    public function approveAndShipReturn(PurchaseReturn $purchaseReturn, int $userId, ?string $shipmentReference = null): PurchaseReturn
    {
        return DB::transaction(function () use ($purchaseReturn, $userId, $shipmentReference): PurchaseReturn {
            $approvedPurchaseReturn = $this->approveAction->execute($purchaseReturn, $userId);

            return $this->shipAction->execute($approvedPurchaseReturn, $userId, $shipmentReference);
        });
    }

    public function completeReturn(PurchaseReturn $purchaseReturn, array $data, int $userId): PurchaseReturn
    {
        return $this->completeAction->execute($purchaseReturn, $data, $userId);
    }

    public function cancelReturn(PurchaseReturn $purchaseReturn, int $userId, ?string $reason = null): PurchaseReturn
    {
        return $this->cancelAction->execute($purchaseReturn, $userId, $reason);
    }

    public function deleteReturn(PurchaseReturn $purchaseReturn): bool
    {
        if (! $purchaseReturn->canEdit()) {
            throw new \Exception('Only draft or pending supplier returns can be deleted.');
        }

        return $purchaseReturn->delete();
    }

    /**
     * @return array<string, int>
     */
    public function getStatistics(?int $shopId = null): array
    {
        $query = PurchaseReturn::query();

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        $stats = (clone $query)->selectRaw("\n            COUNT(*) as total_count,\n            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,\n            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,\n            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_count,\n            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count\n        ")->first();

        return [
            'total_count' => (int) $stats->total_count,
            'pending_count' => (int) $stats->pending_count,
            'approved_count' => (int) $stats->approved_count,
            'shipped_count' => (int) $stats->shipped_count,
            'completed_count' => (int) $stats->completed_count,
            'open_count' => (clone $query)->whereIn('status', [
                PurchaseReturnStatus::PENDING->value,
                PurchaseReturnStatus::APPROVED->value,
                PurchaseReturnStatus::SHIPPED->value,
            ])->count(),
        ];
    }
}
