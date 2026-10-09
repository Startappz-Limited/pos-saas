<?php

namespace App\Http\Requests;

use App\Enums\ProductStatus;
use App\Enums\TaxClass;
use App\Models\Category;
use App\Models\Shop;
use App\Models\Supplier;
use App\Rules\ExistsForViewer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId), 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($productId)],
            'category_id' => ['sometimes', 'required', new ExistsForViewer(Category::class)],
            'supplier_id' => ['nullable', new ExistsForViewer(Supplier::class)],
            'shop_id' => ['sometimes', 'required', new ExistsForViewer(Shop::class)],
            'sync_shops' => ['nullable', 'boolean'],
            'shop_ids' => ['nullable', 'array'],
            'shop_ids.*' => ['integer', new ExistsForViewer(Shop::class)],
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('products', 'barcode')->ignore($productId)],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['nullable', 'boolean'],
            'has_variations' => ['nullable', 'boolean'],
            'variations' => ['nullable', 'array'],
            'variations.*.id' => ['nullable', 'integer', 'exists:product_variations,id'],
            'variations.*.name' => ['required_with:has_variations', 'string', 'max:255'],
            'variations.*.sku' => ['required_with:has_variations', 'string', 'max:255'],
            'variations.*.barcode' => ['nullable', 'string', 'max:255'],
            'variations.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variations.*.selling_price' => ['required_with:has_variations', 'numeric', 'min:0'],
            'variations.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'variations.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'images' => ['nullable', 'array'],
            'unit' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            // Null means inherit the shop's default VAT treatment.
            'tax_class' => ['nullable', Rule::enum(TaxClass::class)],
            'variations.*.tax_class' => ['nullable', Rule::enum(TaxClass::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'sku.unique' => 'This SKU is already in use.',
            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'Selected category does not exist.',
            'shop_id.required' => 'The owning shop is required.',
            'shop_id.exists' => 'Selected shop does not exist.',
            'selling_price.required' => 'Selling price is required.',
            'image.image' => 'The product image must be an image file.',
            'image.max' => 'The product image must not be larger than 2MB.',
        ];
    }
}
