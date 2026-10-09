<?php

namespace App\Services;

use App\Actions\AcknowledgeLowStockAlert;
use App\Actions\CheckLowStockLevels;
use App\Actions\CreateLowStockAlert;
use App\Enums\AlertStatus;
use App\Models\LowStockAlert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AlertService
{
    public function __construct(
        protected CreateLowStockAlert $createAlert,
        protected AcknowledgeLowStockAlert $acknowledgeAlert,
        protected CheckLowStockLevels $checkLevels
    ) {}

    /**
     * Get all alerts with pagination.
     */
    public function getAllAlerts(int $perPage = 15): LengthAwarePaginator
    {
        return LowStockAlert::with(['shop', 'product', 'variation', 'acknowledger'])
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get alerts for a specific shop.
     */
    public function getShopAlerts(int $shopId, int $perPage = 15): LengthAwarePaginator
    {
        return LowStockAlert::with(['product', 'variation', 'acknowledger'])
            ->forShop($shopId)
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get alerts for a specific product.
     */
    public function getProductAlerts(int $productId, int $perPage = 15): LengthAwarePaginator
    {
        return LowStockAlert::with(['shop', 'variation', 'acknowledger'])
            ->forProduct($productId)
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get alert by UUID.
     */
    public function getAlertByUuid(string $uuid): ?LowStockAlert
    {
        return LowStockAlert::with(['shop', 'product', 'variation', 'acknowledger'])
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Create a low stock alert.
     */
    public function createAlert(array $data): LowStockAlert
    {
        return $this->createAlert->execute($data);
    }

    /**
     * Acknowledge an alert.
     */
    public function acknowledgeAlert(LowStockAlert $alert, int $userId, ?string $newStatus = null): LowStockAlert
    {
        return $this->acknowledgeAlert->execute($alert, $userId, $newStatus);
    }

    /**
     * Check low stock levels and create alerts.
     */
    public function checkLowStockLevels(int $shopId, ?int $userId = null): Collection
    {
        return $this->checkLevels->execute($shopId, $userId);
    }

    /**
     * Get pending alerts.
     */
    public function getPendingAlerts(int $perPage = 15): LengthAwarePaginator
    {
        return LowStockAlert::with(['shop', 'product', 'variation'])
            ->pending()
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get acknowledged alerts.
     */
    public function getAcknowledgedAlerts(int $perPage = 15): LengthAwarePaginator
    {
        return LowStockAlert::with(['shop', 'product', 'variation', 'acknowledger'])
            ->acknowledged()
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get resolved alerts.
     */
    public function getResolvedAlerts(int $perPage = 15): LengthAwarePaginator
    {
        return LowStockAlert::with(['shop', 'product', 'variation', 'acknowledger'])
            ->resolved()
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get active alerts (pending or acknowledged).
     */
    public function getActiveAlerts(int $perPage = 15): LengthAwarePaginator
    {
        return LowStockAlert::with(['shop', 'product', 'variation', 'acknowledger'])
            ->active()
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get alerts by status.
     */
    public function getAlertsByStatus(AlertStatus $status, int $perPage = 15): LengthAwarePaginator
    {
        return LowStockAlert::with(['shop', 'product', 'variation', 'acknowledger'])
            ->withStatus($status)
            ->recent()
            ->paginate($perPage);
    }

    /**
     * Get alert statistics for a shop.
     */
    public function getShopAlertStats(int $shopId): array
    {
        $alerts = LowStockAlert::forShop($shopId)->get();

        return [
            'total_alerts' => $alerts->count(),
            'pending' => $alerts->where('status', AlertStatus::PENDING)->count(),
            'acknowledged' => $alerts->where('status', AlertStatus::ACKNOWLEDGED)->count(),
            'resolved' => $alerts->where('status', AlertStatus::RESOLVED)->count(),
            'ignored' => $alerts->where('status', AlertStatus::IGNORED)->count(),
            'active' => $alerts->whereIn('status', [AlertStatus::PENDING, AlertStatus::ACKNOWLEDGED])->count(),
        ];
    }

    /**
     * Resolve an alert.
     */
    public function resolveAlert(LowStockAlert $alert): bool
    {
        return $alert->resolve();
    }

    /**
     * Ignore an alert.
     */
    public function ignoreAlert(LowStockAlert $alert): bool
    {
        return $alert->ignore();
    }

    /**
     * Bulk acknowledge alerts.
     */
    public function bulkAcknowledge(array $alertUuids, int $userId): int
    {
        $count = 0;

        foreach ($alertUuids as $uuid) {
            $alert = $this->getAlertByUuid($uuid);
            if ($alert && $alert->isPending()) {
                $this->acknowledgeAlert($alert, $userId);
                $count++;
            }
        }

        return $count;
    }
}
