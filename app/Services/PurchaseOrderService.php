<?php

namespace App\Services;

use App\Actions\PurchaseOrder\ApprovePurchaseOrderAction;
use App\Actions\PurchaseOrder\CreatePurchaseOrderAction;
use App\Actions\PurchaseOrder\DeletePurchaseOrderAction;
use App\Actions\PurchaseOrder\UpdatePurchaseOrderAction;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        protected CreatePurchaseOrderAction $createAction,
        protected UpdatePurchaseOrderAction $updateAction,
        protected DeletePurchaseOrderAction $deleteAction,
        protected ApprovePurchaseOrderAction $approveAction,
    ) {}

    public function getAllPurchaseOrders(
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?int $supplierId = null,
        ?int $shopId = null,
    ): LengthAwarePaginator {
        $query = PurchaseOrder::query()->with(['supplier', 'shop', 'items']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return $query->latest('order_date')->paginate($perPage);
    }

    public function getDraftPurchaseOrders(): Collection
    {
        return PurchaseOrder::draft()->with(['supplier', 'shop'])->latest()->get();
    }

    public function getPendingPurchaseOrders(): Collection
    {
        return PurchaseOrder::pending()->with(['supplier', 'shop'])->latest()->get();
    }

    public function getOverduePurchaseOrders(): Collection
    {
        return PurchaseOrder::overdue()->with(['supplier', 'shop'])->latest()->get();
    }

    public function createPurchaseOrder(array $data, ?int $userId = null): PurchaseOrder
    {
        return $this->createAction->execute($data, $userId);
    }

    public function updatePurchaseOrder(PurchaseOrder $purchaseOrder, array $data, ?int $userId = null): PurchaseOrder
    {
        return $this->updateAction->execute($purchaseOrder, $data, $userId);
    }

    public function deletePurchaseOrder(PurchaseOrder $purchaseOrder): bool
    {
        return $this->deleteAction->execute($purchaseOrder);
    }

    public function approvePurchaseOrder(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        return $this->approveAction->execute($purchaseOrder, $userId);
    }

    public function approveAndMarkAsOrdered(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $userId) {
            $approvedPurchaseOrder = $this->approvePurchaseOrder($purchaseOrder, $userId);

            return $this->markAsOrdered($approvedPurchaseOrder, $userId);
        });
    }

    public function submitApproveAndMarkAsOrdered(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $userId) {
            $pendingPurchaseOrder = $this->submitForApproval($purchaseOrder, $userId);
            $approvedPurchaseOrder = $this->approvePurchaseOrder($pendingPurchaseOrder, $userId);

            return $this->markAsOrdered($approvedPurchaseOrder, $userId);
        });
    }

    public function submitForApproval(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->isDraft()) {
            throw new \Exception('Only draft purchase orders can be submitted for approval.');
        }

        return $this->changeStatus($purchaseOrder, PurchaseOrderStatus::PENDING, $userId);
    }

    public function changeStatus(PurchaseOrder $purchaseOrder, PurchaseOrderStatus $status, ?int $userId = null): PurchaseOrder
    {
        $purchaseOrder->update([
            'status' => $status,
            'updated_by' => $userId,
        ]);

        return $purchaseOrder->fresh();
    }

    public function cancelPurchaseOrder(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->canCancel()) {
            throw new \Exception('Purchase order cannot be cancelled in current status.');
        }

        return $this->changeStatus($purchaseOrder, PurchaseOrderStatus::CANCELLED, $userId);
    }

    public function markAsOrdered(PurchaseOrder $purchaseOrder, ?int $userId = null): PurchaseOrder
    {
        if (! $purchaseOrder->isApproved()) {
            throw new \Exception('Only approved purchase orders can be marked as ordered.');
        }

        return $this->changeStatus($purchaseOrder, PurchaseOrderStatus::ORDERED, $userId);
    }

    public function getStatistics(): array
    {
        return [
            'total_count' => PurchaseOrder::count(),
            'draft_count' => PurchaseOrder::draft()->count(),
            'pending_count' => PurchaseOrder::pending()->count(),
            'approved_count' => PurchaseOrder::approved()->count(),
            'ordered_count' => PurchaseOrder::ordered()->count(),
            'received_count' => PurchaseOrder::received()->count(),
            'overdue_count' => PurchaseOrder::overdue()->count(),
            'total_amount' => PurchaseOrder::sum('total_amount'),
            'average_order_value' => PurchaseOrder::avg('total_amount'),
        ];
    }
}
