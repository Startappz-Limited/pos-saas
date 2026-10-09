<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductPurchaseCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('setCost', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'costs' => ['required', 'array'],
            'costs.product' => ['nullable', 'array'],
            'costs.product.*' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'costs.variation' => ['nullable', 'array'],
            'costs.variation.*' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'redirect_to' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'costs.product.*.numeric' => __('Each purchase cost must be a number.'),
            'costs.product.*.min' => __('A purchase cost cannot be negative.'),
            'costs.variation.*.numeric' => __('Each purchase cost must be a number.'),
            'costs.variation.*.min' => __('A purchase cost cannot be negative.'),
        ];
    }

    /**
     * @return array<int, float|string|null>
     */
    public function productCosts(): array
    {
        return $this->validated()['costs']['product'] ?? [];
    }

    /**
     * @return array<int, float|string|null>
     */
    public function variationCosts(): array
    {
        return $this->validated()['costs']['variation'] ?? [];
    }
}
