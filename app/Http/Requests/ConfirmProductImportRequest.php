<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The answer on the confirmation page when a file holds products that already
 * exist (fix/product-stock-import).
 */
class ConfirmProductImportRequest extends FormRequest
{
    public const UPDATE = 'update';

    public const NEW_ONLY = 'new_only';

    public const CANCEL = 'cancel';

    public function authorize(): bool
    {
        return $this->user()->can('import', Product::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['decision' => ['required', Rule::in([self::UPDATE, self::NEW_ONLY, self::CANCEL])]];
    }
}
