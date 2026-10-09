<?php

namespace App\Services;

use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\Refund;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    public function getAllReturns(
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?string $reason = null,
        ?int $shopId = null,
    ): LengthAwarePaginator {
        $query = SaleReturn::query()
            ->with(['sale.customer:id,name', 'customer:id,name', 'shop', 'requestedBy', 'items.product']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($reason) {
            $query->where('reason', $reason);
        }

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return $query->latest()->paginate($perPage);
    }

    public function getReturnsForSale(Sale $sale): Collection
    {
        return SaleReturn::query()
            ->where('sale_id', $sale->id)
            ->with(['items.product', 'requestedBy', 'refund'])
            ->latest()
            ->get();
    }

    public function createReturn(array $data, int $userId): SaleReturn
    {
        return DB::transaction(function () use ($data, $userId) {
            $sale = Sale::findOrFail($data['sale_id']);
            $reason = ReturnReason::from($data['reason']);

            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'shop_id' => $sale->shop_id,
                'status' => ReturnStatus::PENDING,
                'reason' => $reason,
                'notes' => $data['notes'] ?? null,
                'restocking_fee' => $data['restocking_fee'] ?? 0,
                'requested_by' => $userId,
            ]);

            // Bulk-fetch sale items to avoid N+1 queries in loop
            $saleItemIds = collect($data['items'])->pluck('sale_item_id');
            $saleItems = SaleItem::whereIn('id', $saleItemIds)->get()->keyBy('id');

            $totalAmount = 0;

            foreach ($data['items'] as $itemData) {
                $saleItem = $saleItems->get($itemData['sale_item_id']);
                if (! $saleItem) {
                    throw new ModelNotFoundException("SaleItem [{$itemData['sale_item_id']}] not found.");
                }
                $quantity = (int) $itemData['quantity'];
                $unitPrice = $saleItem->unit_price ?? $saleItem->price ?? 0;
                $totalPrice = $unitPrice * $quantity;
                $totalAmount += $totalPrice;

                ReturnItem::create([
                    'return_id' => $saleReturn->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'condition' => $itemData['condition'] ?? null,
                    'condition_notes' => $itemData['condition_notes'] ?? null,
                    'is_restockable' => $reason->isRestockable(),
                    'return_to_supplier' => (bool) ($itemData['return_to_supplier'] ?? false),
                    'return_to_supplier_at' => ! empty($itemData['return_to_supplier']) ? now() : null,
                    'return_to_supplier_notes' => $itemData['return_to_supplier_notes'] ?? null,
                ]);
            }

            $restockingFee = $data['restocking_fee'] ?? 0;
            $refundAmount = $totalAmount - $restockingFee;

            $saleReturn->update([
                'total_amount' => $totalAmount,
                'refund_amount' => max(0, $refundAmount),
            ]);

            return $saleReturn->load(['sale', 'customer', 'shop', 'requestedBy', 'items.product']);
        });
    }

    public function updateReturn(SaleReturn $saleReturn, array $data): SaleReturn
    {
        if (! $saleReturn->isPending()) {
            throw new \Exception('Only pending returns can be updated.');
        }

        return DB::transaction(function () use ($saleReturn, $data) {
            $reason = isset($data['reason']) ? ReturnReason::from($data['reason']) : $saleReturn->reason;

            $saleReturn->update([
                'reason' => $reason,
                'notes' => $data['notes'] ?? $saleReturn->notes,
                'restocking_fee' => $data['restocking_fee'] ?? $saleReturn->restocking_fee,
            ]);

            if (isset($data['items'])) {
                $saleReturn->items()->delete();

                // Bulk-fetch sale items to avoid N+1 queries in loop
                $saleItemIds = collect($data['items'])->pluck('sale_item_id');
                $saleItems = SaleItem::whereIn('id', $saleItemIds)->get()->keyBy('id');

                $totalAmount = 0;

                foreach ($data['items'] as $itemData) {
                    $saleItem = $saleItems->get($itemData['sale_item_id']);
                    if (! $saleItem) {
                        throw new ModelNotFoundException("SaleItem [{$itemData['sale_item_id']}] not found.");
                    }
                    $quantity = (int) $itemData['quantity'];
                    $unitPrice = $saleItem->unit_price ?? $saleItem->price ?? 0;
                    $totalPrice = $unitPrice * $quantity;
                    $totalAmount += $totalPrice;

                    ReturnItem::create([
                        'return_id' => $saleReturn->id,
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $saleItem->product_id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $totalPrice,
                        'condition' => $itemData['condition'] ?? null,
                        'condition_notes' => $itemData['condition_notes'] ?? null,
                        'is_restockable' => $reason->isRestockable(),
                        'return_to_supplier' => (bool) ($itemData['return_to_supplier'] ?? false),
                        'return_to_supplier_at' => ! empty($itemData['return_to_supplier']) ? now() : null,
                        'return_to_supplier_notes' => $itemData['return_to_supplier_notes'] ?? null,
                    ]);
                }

                $restockingFee = $data['restocking_fee'] ?? $saleReturn->restocking_fee;
                $refundAmount = $totalAmount - $restockingFee;

                $saleReturn->update([
                    'total_amount' => $totalAmount,
                    'refund_amount' => max(0, $refundAmount),
                ]);
            }

            return $saleReturn->fresh()->load(['sale', 'customer', 'shop', 'requestedBy', 'items.product']);
        });
    }

    public function approveReturn(SaleReturn $saleReturn, int $userId): SaleReturn
    {
        if (! $saleReturn->canBeApproved()) {
            throw new \Exception('Return cannot be approved in its current status.');
        }

        $saleReturn->update([
            'status' => ReturnStatus::APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $saleReturn->fresh();
    }

    public function rejectReturn(SaleReturn $saleReturn, int $userId, ?string $reason = null): SaleReturn
    {
        if (! $saleReturn->canBeRejected()) {
            throw new \Exception('Return cannot be rejected in its current status.');
        }

        $saleReturn->update([
            'status' => ReturnStatus::REJECTED,
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $saleReturn->fresh();
    }

    public function receiveReturn(SaleReturn $saleReturn): SaleReturn
    {
        if (! $saleReturn->canBeReceived()) {
            throw new \Exception('Return must be approved before items can be received.');
        }

        $saleReturn->update([
            'status' => ReturnStatus::RECEIVED,
            'received_at' => now(),
        ]);

        return $saleReturn->fresh();
    }

    public function inspectReturn(SaleReturn $saleReturn, int $userId, ?string $inspectionNotes = null): SaleReturn
    {
        if (! $saleReturn->canBeInspected()) {
            throw new \Exception('Return must be received before inspection.');
        }

        $saleReturn->update([
            'status' => ReturnStatus::INSPECTED,
            'inspected_at' => now(),
            'inspected_by' => $userId,
            'inspection_notes' => $inspectionNotes,
        ]);

        return $saleReturn->fresh();
    }

    public function createRefund(SaleReturn $saleReturn, array $data, int $userId): Refund
    {
        if (! $saleReturn->canBeRefunded()) {
            throw new \Exception('Return is not eligible for a refund in its current status.');
        }

        return DB::transaction(function () use ($saleReturn, $data, $userId) {
            $refund = Refund::create([
                'return_id' => $saleReturn->id,
                'customer_id' => $saleReturn->customer_id,
                'shop_id' => $saleReturn->shop_id,
                'method' => $data['method'],
                'amount' => $data['amount'],
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'processed_by' => $userId,
            ]);

            $saleReturn->update(['status' => ReturnStatus::COMPLETED]);

            return $refund->load(['saleReturn', 'customer', 'shop', 'processedBy']);
        });
    }

    public function processRefund(Refund $refund, int $userId, ?string $transactionId = null): Refund
    {
        if (! $refund->isPending()) {
            throw new \Exception('Refund has already been processed.');
        }

        $refund->update([
            'status' => 'completed',
            'processed_by' => $userId,
            'processed_at' => now(),
            'transaction_id' => $transactionId,
        ]);

        return $refund->fresh();
    }

    public function deleteReturn(SaleReturn $saleReturn): bool
    {
        if (! $saleReturn->isPending()) {
            throw new \Exception('Only pending returns can be deleted.');
        }

        return DB::transaction(function () use ($saleReturn) {
            $saleReturn->items()->delete();

            return $saleReturn->delete();
        });
    }

    /**
     * @return array<string, int>
     */
    public function getStatistics(?int $shopId = null): array
    {
        $query = SaleReturn::query();

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        $stats = (clone $query)->selectRaw("
            COUNT(*) as total_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
        ")->first();

        return [
            'total_count' => (int) $stats->total_count,
            'pending_count' => (int) $stats->pending_count,
            'approved_count' => (int) $stats->approved_count,
            'completed_count' => (int) $stats->completed_count,
            'rejected_count' => (int) $stats->rejected_count,
        ];
    }
}
