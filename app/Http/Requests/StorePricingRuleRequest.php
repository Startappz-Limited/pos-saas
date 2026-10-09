<?php

namespace App\Http\Requests;

use App\Enums\PricingType;
use App\Models\Product;
use App\Rules\ExistsForViewer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePricingRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::enum(PricingType::class)],
            'product_id' => ['required', new ExistsForViewer(Product::class)],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'max_quantity' => ['nullable', 'integer', 'min:1', 'gte:min_quantity'],
            'customer_id' => ['nullable', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The pricing rule name is required.',
            'type.required' => 'The pricing type is required.',
            'product_id.required' => 'A product must be selected.',
            'product_id.exists' => 'The selected product does not exist.',
            'price.required' => 'The price is required.',
            'price.min' => 'The price must be at least 0.',
            'discount_percentage.max' => 'The discount percentage cannot exceed 100%.',
            'max_quantity.gte' => 'The maximum quantity must be greater than or equal to the minimum quantity.',
            'end_date.after' => 'The end date must be after the start date.',
        ];
    }
}
