<?php

namespace App\Http\Requests;

use App\Enums\RefundMethod;
use Illuminate\Foundation\Http\FormRequest;

class ProcessRefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'return_id' => ['required', 'exists:returns,id'],
            'method' => ['required', 'string', 'in:' . implode(',', array_column(RefundMethod::cases(), 'value'))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'return_id.required' => 'A return must be selected for this refund.',
            'return_id.exists' => 'The selected return does not exist.',
            'method.required' => 'A refund method must be selected.',
            'amount.required' => 'The refund amount is required.',
            'amount.min' => 'The refund amount must be at least 0.01.',
        ];
    }
}
