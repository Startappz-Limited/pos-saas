<?php

namespace App\Actions\Category;

use App\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Support\Str;

class CreateCategoryAction
{
    public function execute(array $data): Category
    {
        $data['uuid'] = (string) Str::uuid();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['status'] = $data['status'] ?? CategoryStatus::ACTIVE;
        $data['order'] = $data['order'] ?? 0;
        $data['created_by'] = $data['created_by'] ?? auth()->id();

        return Category::create($data);
    }
}
