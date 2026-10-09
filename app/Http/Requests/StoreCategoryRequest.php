<?php

namespace App\Http\Requests;

use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Rules\ExistsForViewer;
use App\Rules\UniqueInBusiness;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', UniqueInBusiness::for('categories', 'name')],
            'slug' => ['nullable', 'string', 'max:255', UniqueInBusiness::for('categories', 'slug'), 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', new ExistsForViewer(Category::class)],
            'image' => ['nullable', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::enum(CategoryStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'name.unique' => 'A category with this name already exists.',
            'slug.unique' => 'A category with this slug already exists.',
            'slug.regex' => 'Slug must contain only lowercase letters, numbers, and hyphens.',
            'parent_id.exists' => 'Selected parent category does not exist.',
        ];
    }
}
