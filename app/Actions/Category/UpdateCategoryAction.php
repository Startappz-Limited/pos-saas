<?php

namespace App\Actions\Category;

use App\Models\Category;
use Illuminate\Support\Str;

class UpdateCategoryAction
{
    public function execute(Category $category, array $data): Category
    {
        // Update slug if name changed and no custom slug provided
        if (isset($data['name']) && $data['name'] !== $category->name && ! isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Prevent category from being its own parent
        if (isset($data['parent_id']) && $data['parent_id'] == $category->id) {
            unset($data['parent_id']);
        }

        // Prevent circular parent relationships
        if (isset($data['parent_id']) && $data['parent_id']) {
            $parent = Category::find($data['parent_id']);
            if ($parent && $this->isDescendant($category, $parent)) {
                unset($data['parent_id']);
            }
        }

        $data['updated_by'] = $data['updated_by'] ?? auth()->id();

        $category->update($data);

        return $category->fresh();
    }

    private function isDescendant(Category $category, Category $potentialAncestor): bool
    {
        $parent = $potentialAncestor->parent;

        while ($parent) {
            if ($parent->id === $category->id) {
                return true;
            }
            $parent = $parent->parent;
        }

        return false;
    }
}
