<?php

namespace App\Services;

use App\Actions\Supplier\CreateSupplierAction;
use App\Actions\Supplier\DeleteSupplierAction;
use App\Actions\Supplier\UpdateSupplierAction;
use App\Enums\SupplierStatus;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SupplierService
{
    public function __construct(
        protected CreateSupplierAction $createAction,
        protected UpdateSupplierAction $updateAction,
        protected DeleteSupplierAction $deleteAction
    ) {}

    public function getAllSuppliers(
        int $perPage = 15,
        ?string $search = null,
        ?SupplierStatus $status = null,
        ?string $country = null
    ): LengthAwarePaginator {
        $query = Supplier::with(['creator', 'updater'])
            ->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->byStatus($status);
        }

        if ($country) {
            $query->byCountry($country);
        }

        return $query->paginate($perPage);
    }

    public function getActiveSuppliers(): Collection
    {
        return Supplier::active()
            ->orderBy('name')
            ->get();
    }

    public function getTopSuppliers(int $limit = 10): Collection
    {
        return Supplier::active()
            ->withHighRating()
            ->orderBy('total_orders', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getSuppliersNearCreditLimit(): Collection
    {
        return Supplier::active()
            ->nearCreditLimit()
            ->orderBy('credit_utilization', 'desc')
            ->get();
    }

    public function createSupplier(array $data): Supplier
    {
        return $this->createAction->execute($data);
    }

    public function updateSupplier(Supplier $supplier, array $data): Supplier
    {
        return $this->updateAction->execute($supplier, $data);
    }

    public function deleteSupplier(Supplier $supplier): bool
    {
        return $this->deleteAction->execute($supplier);
    }

    public function activateSupplier(Supplier $supplier): Supplier
    {
        return $this->updateSupplier($supplier, ['status' => SupplierStatus::ACTIVE]);
    }

    public function deactivateSupplier(Supplier $supplier): Supplier
    {
        return $this->updateSupplier($supplier, ['status' => SupplierStatus::INACTIVE]);
    }

    public function suspendSupplier(Supplier $supplier): Supplier
    {
        return $this->updateSupplier($supplier, ['status' => SupplierStatus::SUSPENDED]);
    }

    public function blacklistSupplier(Supplier $supplier): Supplier
    {
        return $this->updateSupplier($supplier, ['status' => SupplierStatus::BLACKLISTED]);
    }

    public function updateBalance(Supplier $supplier, float $amount, string $operation = 'add'): Supplier
    {
        $currentBalance = (float) $supplier->current_balance;

        $newBalance = $operation === 'add'
            ? $currentBalance + $amount
            : $currentBalance - $amount;

        return $this->updateSupplier($supplier, [
            'current_balance' => max(0, $newBalance),
        ]);
    }

    public function recordOrder(Supplier $supplier, float $amount): Supplier
    {
        return $this->updateSupplier($supplier, [
            'total_orders' => (float) $supplier->total_orders + $amount,
            'order_count' => $supplier->order_count + 1,
        ]);
    }

    public function recordDelivery(Supplier $supplier, bool $onTime = true): Supplier
    {
        return $this->updateSupplier($supplier, [
            'on_time_deliveries' => $supplier->on_time_deliveries + ($onTime ? 1 : 0),
            'late_deliveries' => $supplier->late_deliveries + ($onTime ? 0 : 1),
        ]);
    }

    public function updateRating(Supplier $supplier, float $rating): Supplier
    {
        // Calculate new average rating
        $currentRating = $supplier->average_rating ?? 0;
        $totalRatings = $supplier->order_count;

        if ($totalRatings === 0) {
            $newRating = $rating;
        } else {
            $newRating = (($currentRating * $totalRatings) + $rating) / ($totalRatings + 1);
        }

        return $this->updateSupplier($supplier, [
            'average_rating' => round($newRating, 2),
        ]);
    }

    public function getStatistics(): array
    {
        $stats = Supplier::query()->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
            SUM(CASE WHEN status = 'blacklisted' THEN 1 ELSE 0 END) as blacklisted,
            COALESCE(SUM(CASE WHEN credit_limit > 0 THEN credit_limit ELSE 0 END), 0) as total_credit_limit,
            COALESCE(SUM(current_balance), 0) as total_balance,
            SUM(CASE WHEN credit_limit > 0 AND current_balance >= (credit_limit * 0.9) THEN 1 ELSE 0 END) as suppliers_near_limit,
            AVG(CASE WHEN average_rating IS NOT NULL THEN average_rating END) as average_rating,
            COALESCE(SUM(total_orders), 0) as total_orders_value
        ")->first();

        return [
            'total' => (int) $stats->total,
            'active' => (int) $stats->active,
            'inactive' => (int) $stats->inactive,
            'suspended' => (int) $stats->suspended,
            'blacklisted' => (int) $stats->blacklisted,
            'total_credit_limit' => (float) $stats->total_credit_limit,
            'total_balance' => (float) $stats->total_balance,
            'suppliers_near_limit' => (int) $stats->suppliers_near_limit,
            'average_rating' => $stats->average_rating ? round((float) $stats->average_rating, 2) : null,
            'total_orders_value' => (float) $stats->total_orders_value,
        ];
    }
}
