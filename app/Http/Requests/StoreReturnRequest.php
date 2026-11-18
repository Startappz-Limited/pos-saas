<?php

namespace App\Http\Requests;

use App\Enums\ReturnReason;
use App\Support\Stock\ReturnQuantities;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'sale_id' => ['required', 'exists:sales,id'],
            'reason' => ['required', 'string', 'in:'.implode(',', array_column(ReturnReason::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'restocking_fee' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'exists:sale_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
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
            'items.*.quantity.min' => 'Return quantity must be greater than 0.',
        ];
    }

    /**
     * A line's quantity is in the unit it was sold in, so its decimals are checked against that unit
     * (stage 11): a whole-unit line refuses a fraction, a weight line takes its three decimals.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (ReturnQuantities::errors((array) $this->input('items', [])) as $key => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($key, $message);
                }
            }
        });
    }
}
