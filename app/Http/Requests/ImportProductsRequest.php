<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Uploading a product file (fix/product-stock-import): who may import, the
 * shop the file is for, and the CSV itself.
 */
class ImportProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('import', Product::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => __('Choose a CSV file. In Excel or Google Sheets, save or download the sheet as "CSV".'),
            'file.max' => __('The file is larger than 2 MB. Split it and import the parts.'),
        ];
    }
}
