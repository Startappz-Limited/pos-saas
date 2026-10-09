<?php

namespace App\Services;

use App\Enums\AdjustmentType;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function getAllAdjustments(
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?string $type = null,
        ?int $shopId = null,
    ): LengthAwarePaginator {
        $query = StockAdjustment::query()
            ->with(['shop', 'creator', 'approver', 'items.product']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('adjustment_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return $query->latest()->paginate($perPage);
    }

    public function getPendingAdjustments(): Collection
    {
        return StockAdjustment::pending()
            ->with(['shop', 'creator', 'items.product'])
            ->latest()
            ->get();
    }

    public function createAdjustment(array $data, ?int $userId = null): StockAdjustment
    {
        return DB::transaction(function () use ($data, $userId) {
            $adjustment = StockAdjustment::create([
                'shop_id' => $data['shop_id'],
                'type' => $data['type'],
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'created_by' => $userId,
            ]);

            if (! empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->addItem($adjustment, $item);
                }
            }

            return $adjustment->load(['shop', 'creator', 'items.product']);
        });
    }

    public function updateAdjustment(StockAdjustment $adjustment, array $data): StockAdjustment
    {
        if (! $adjustment->isPending()) {
            throw new \Exception('Only pending adjustments can be updated.');
        }

        return DB::transaction(function () use ($adjustment, $data) {
            $adjustment->update([
                'type' => $data['type'] ?? $adjustment->type,
                'reason' => $data['reason'] ?? $adjustment->reason,
                'notes' => $data['notes'] ?? $adjustment->notes,
            ]);

            if (isset($data['items'])) {
                // Remove existing items
                $adjustment->items()->delete();

                // Add new items
                foreach ($data['items'] as $item) {
                    $this->addItem($adjustment, $item);
                }
            }

            return $adjustment->fresh()->load(['shop', 'creator', 'items.product']);
        });
    }

    public function addItem(StockAdjustment $adjustment, array $itemData): StockAdjustmentItem
    {
        $product = Product::findOrFail($itemData['product_id']);

        // Get current stock quantity
        $currentStock = $product->stock_quantity ?? 0;
        $quantityChange = (int) $itemData['quantity_change'];

        // Calculate quantity after based on adjustment type
        $quantityAfter = $adjustment->type === AdjustmentType::INCREASE
            ? $currentStock + $quantityChange
            : $currentStock - $quantityChange;

        return StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $product->id,
            'shop_id' => $adjustment->shop_id,
            'quantity_before' => $currentStock,
            'quantity_change' => $quantityChange,
            'quantity_after' => $quantityAfter,
            'item_notes' => $itemData['item_notes'] ?? null,
        ]);
    }

    public function approveAdjustment(StockAdjustment $adjustment, int $userId): StockAdjustment
    {
        if (! $adjustment->canBeApproved()) {
            throw new \Exception('Adjustment cannot be approved in current status.');
        }

        $adjustment->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $adjustment->fresh();
    }

    public function rejectAdjustment(StockAdjustment $adjustment, int $userId, ?string $reason = null): StockAdjustment
    {
        if (! $adjustment->canBeRejected()) {
            throw new \Exception('Adjustment cannot be rejected in current status.');
        }

        $adjustment->update([
            'status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $adjustment->fresh();
    }

    public function completeAdjustment(StockAdjustment $adjustment): StockAdjustment
    {
        if (! $adjustment->canBeCompleted()) {
            throw new \Exception('Adjustment must be approved before completion.');
        }

        return DB::transaction(function () use ($adjustment) {
            // Apply stock changes to products
            foreach ($adjustment->items as $item) {
                $product = $item->product;
                $currentStock = $product->stock_quantity ?? 0;

                $newStock = $adjustment->type === AdjustmentType::INCREASE
                    ? $currentStock + $item->quantity_change
                    : $currentStock - $item->quantity_change;

                // Ensure stock doesn't go negative
                if ($newStock < 0) {
                    throw new \Exception("Cannot complete adjustment: would result in negative stock for {$product->name}.");
                }

                $product->update(['stock_quantity' => $newStock]);
            }

            $adjustment->update(['status' => 'completed']);

            return $adjustment->fresh();
        });
    }

    public function deleteAdjustment(StockAdjustment $adjustment): bool
    {
        if (! $adjustment->isPending()) {
            throw new \Exception('Only pending adjustments can be deleted.');
        }

        return $adjustment->delete();
    }

    public function getStatistics(?int $shopId = null): array
    {
        $query = StockAdjustment::query();

        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return [
            'total_count' => (clone $query)->count(),
            'pending_count' => (clone $query)->pending()->count(),
            'approved_count' => (clone $query)->approved()->count(),
            'completed_count' => (clone $query)->completed()->count(),
            'rejected_count' => (clone $query)->rejected()->count(),
            'increase_count' => (clone $query)->where('type', AdjustmentType::INCREASE)->count(),
            'decrease_count' => (clone $query)->where('type', AdjustmentType::DECREASE)->count(),
        ];
    }
}
