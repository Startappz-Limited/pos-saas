<?php

namespace App\Services;

use App\Actions\Shop\CreateShopAction;
use App\Actions\Shop\DeleteShopAction;
use App\Actions\Shop\UpdateShopAction;
use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ShopService
{
    public function __construct(
        protected CreateShopAction $createAction,
        protected UpdateShopAction $updateAction,
        protected DeleteShopAction $deleteAction
    ) {}

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Shop::query()->with(['manager', 'creator', 'updater']);

        $this->applyFilters($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    public function getAll(): Collection
    {
        return Shop::with(['manager', 'creator', 'updater'])->latest()->get();
    }

    public function getActive(): Collection
    {
        return Shop::active()->with(['manager', 'creator', 'updater'])->latest()->get();
    }

    public function getInactive(): Collection
    {
        return Shop::inactive()->with(['manager', 'creator', 'updater'])->latest()->get();
    }

    public function getSuspended(): Collection
    {
        return Shop::suspended()->with(['manager', 'creator', 'updater'])->latest()->get();
    }

    public function getById(int|string $id): ?Shop
    {
        return Shop::with(['manager', 'creator', 'updater', 'users'])->find($id);
    }

    public function getByUuid(string $uuid): ?Shop
    {
        return Shop::with(['manager', 'creator', 'updater', 'users'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function getByCode(string $code): ?Shop
    {
        return Shop::where('code', $code)->first();
    }

    public function create(array $data): Shop
    {
        return $this->createAction->execute($data);
    }

    public function update(Shop $shop, array $data): Shop
    {
        return $this->updateAction->execute($shop, $data);
    }

    public function delete(Shop $shop): bool
    {
        return $this->deleteAction->execute($shop);
    }

    public function activate(Shop $shop): bool
    {
        return $shop->activate();
    }

    public function deactivate(Shop $shop): bool
    {
        return $shop->deactivate();
    }

    public function suspend(Shop $shop): bool
    {
        return $shop->suspend();
    }

    public function changeStatus(Shop $shop, ShopStatus $status): bool
    {
        $shop->status = $status;

        return $shop->save();
    }

    public function assignManager(Shop $shop, int $managerId): Shop
    {
        $data = ['manager_id' => $managerId];

        return $this->update($shop, $data);
    }

    public function assignUsers(Shop $shop, array $userIds): Shop
    {
        $shop->users()->sync($userIds);
        User::forgetShopAccessCache();

        return $shop->fresh(['users']);
    }

    public function updateSettings(Shop $shop, array $settings): Shop
    {
        $currentSettings = $shop->settings ?? [];
        $newSettings = array_merge($currentSettings, $settings);

        return $this->update($shop, ['settings' => $newSettings]);
    }

    public function getStatistics(): array
    {
        return [
            'total' => Shop::count(),
            'active' => Shop::active()->count(),
            'inactive' => Shop::inactive()->count(),
            'suspended' => Shop::suspended()->count(),
        ];
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['manager_id'])) {
            $query->where('manager_id', $filters['manager_id']);
        }

        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (! empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }
    }
}
