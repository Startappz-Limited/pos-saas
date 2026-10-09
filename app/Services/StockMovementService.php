<?php

namespace App\Services;

use App\Actions\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Models\StockMovement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class StockMovementService
{
    public function __construct(
        protected RecordStockMovement $recordMovement
    ) {}

    /**
     * Get all stock movements with pagination.
     */
    public function getAllMovements(int $perPage = 15): LengthAwarePaginator
    {
        return StockMovement::with(['shop', 'product', 'variation', 'creator'])
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get movements for a specific shop.
     */
    public function getShopMovements(int $shopId, int $perPage = 15): LengthAwarePaginator
    {
        return StockMovement::with(['product', 'variation', 'creator'])
            ->forShop($shopId)
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get movements for a specific product.
     */
    public function getProductMovements(int $productId, ?int $variationId = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = StockMovement::with(['shop', 'variation', 'creator'])
            ->forProduct($productId);

        if ($variationId) {
            $query->where('variation_id', $variationId);
        }

        return $query->recent()->paginate($perPage);
    }

    /**
     * Get movement by UUID.
     */
    public function getMovementByUuid(string $uuid): ?StockMovement
    {
        return StockMovement::with(['shop', 'product', 'variation', 'creator', 'reference'])
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Record a stock movement.
     */
    public function recordMovement(array $data): StockMovement
    {
        return $this->recordMovement->execute($data);
    }

    /**
     * Get movements by type.
     */
    public function getMovementsByType(StockMovementType $type, int $perPage = 15): LengthAwarePaginator
    {
        return StockMovement::with(['shop', 'product', 'variation', 'creator'])
            ->ofType($type)
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get addition movements.
     */
    public function getAdditions(int $perPage = 15): LengthAwarePaginator
    {
        return StockMovement::with(['shop', 'product', 'variation', 'creator'])
            ->additions()
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get deduction movements.
     */
    public function getDeductions(int $perPage = 15): LengthAwarePaginator
    {
        return StockMovement::with(['shop', 'product', 'variation', 'creator'])
            ->deductions()
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get movement statistics for a shop.
     */
    public function getShopMovementStats(int $shopId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = StockMovement::forShop($shopId);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $movements = $query->get();

        return [
            'total_movements' => $movements->count(),
            'total_additions' => $movements->filter(fn ($m) => $m->movement_type->isAddition())->count(),
            'total_deductions' => $movements->filter(fn ($m) => $m->movement_type->isDeduction())->count(),
            'quantity_added' => $movements->filter(fn ($m) => $m->movement_type->isAddition())->sum('quantity'),
            'quantity_deducted' => $movements->filter(fn ($m) => $m->movement_type->isDeduction())->sum('quantity'),
            'by_type' => $movements->groupBy('movement_type')->map(fn ($items) => [
                'count' => $items->count(),
                'quantity' => $items->sum('quantity'),
            ]),
        ];
    }

    /**
     * Get recent movements.
     */
    public function getRecentMovements(int $limit = 10): Collection
    {
        return StockMovement::with(['shop', 'product', 'variation', 'creator'])
            ->recent()
            ->limit($limit)
            ->get();
    }
}
