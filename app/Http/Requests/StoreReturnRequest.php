<?php

namespace App\Http\Requests;

use App\Enums\ReturnReason;
use App\Models\Sale;
use App\Rules\ExistsForViewer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sale_id' => ['required', new ExistsForViewer(Sale::class)],
            'reason' => ['required', 'string', 'in:'.implode(',', array_column(ReturnReason::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'restocking_fee' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'exists:sale_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.condition' => ['nullable', 'string', 'in:new,opened,damaged,defective'],
            'items.*.condition_notes' => ['nullable', 'string', 'max:500'],
            'items.*.return_to_supplier' => ['nullable', 'boolean'],
            'items.*.return_to_supplier_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sale_id.required' => 'A sale must be selected for this return.',
            'sale_id.exists' => 'The selected sale does not exist.',
            'reason.required' => 'A return reason must be provided.',
            'items.required' => 'At least one item must be selected for return.',
            'items.min' => 'At least one item must be selected for return.',
            'items.*.sale_item_id.required' => 'Each return item must reference a sale item.',
            'items.*.quantity.required' => 'Quantity is required for each return item.',
            'items.*.quantity.min' => 'Return quantity must be at least 1.',
        ];
    }
}
