<?php

namespace App\Http\Requests;

use App\Models\StockAdjustment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WriteOffExpiredStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockAdjustment::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // `exists` is viewer-aware (another business's shop, or one the user can't reach, fails).
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
        ];
    }
}
