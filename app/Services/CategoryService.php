<?php

namespace App\Services;

use App\Actions\Category\CreateCategoryAction;
use App\Actions\Category\DeleteCategoryAction;
use App\Actions\Category\UpdateCategoryAction;
use App\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function __construct(
        private CreateCategoryAction $createAction,
        private UpdateCategoryAction $updateAction,
        private DeleteCategoryAction $deleteAction
    ) {}

    public function getAllCategories(int $perPage = 15, ?string $search = null, ?string $status = null): LengthAwarePaginator
    {
        $query = Category::query()->with(['parent', 'creator', 'updater']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('order')->orderBy('name')->paginate($perPage);
    }

    public function getRootCategories(?string $status = null): Collection
    {
        $query = Category::query()->rootCategories()->with(['children']);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->ordered()->get();
    }

    public function getCategoryTree(?string $status = null): Collection
    {
        $query = Category::query()->rootCategories()->with('allChildren');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->ordered()->get();
    }

    public function createCategory(array $data): Category
    {
        return $this->createAction->execute($data);
    }

    public function updateCategory(Category $category, array $data): Category
    {
        return $this->updateAction->execute($category, $data);
    }

    public function deleteCategory(Category $category): bool
    {
        // Reassign children to parent's parent (or null if root)
        if ($category->children()->exists()) {
            $category->children()->update(['parent_id' => $category->parent_id]);
        }

        return $this->deleteAction->execute($category);
    }

    public function activateCategory(Category $category): Category
    {
        return $this->updateCategory($category, ['status' => CategoryStatus::ACTIVE]);
    }

    public function deactivateCategory(Category $category): Category
    {
        return $this->updateCategory($category, ['status' => CategoryStatus::INACTIVE]);
    }

    public function reorderCategories(array $categoryOrders): void
    {
        if (empty($categoryOrders)) {
            return;
        }

        Category::upsert(
            collect($categoryOrders)->map(fn ($id, $order) => ['id' => $id, 'order' => $order])->values()->all(),
            ['id'],
            ['order']
        );
    }

    public function getStatistics(): array
    {
        $stats = Category::query()->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN parent_id IS NULL THEN 1 ELSE 0 END) as root_categories
        ")->first();

        $withChildren = Category::has('children')->count();

        return [
            'total' => (int) $stats->total,
            'active' => (int) $stats->active,
            'inactive' => (int) $stats->inactive,
            'root_categories' => (int) $stats->root_categories,
            'with_children' => $withChildren,
        ];
    }
}
