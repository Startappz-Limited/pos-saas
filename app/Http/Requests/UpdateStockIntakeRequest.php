<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockIntakeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('stock_intakes.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'intake_date' => ['sometimes', 'required', 'date'],
            'quantity_received' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'quantity_accepted' => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_rejected' => ['sometimes', 'required', 'numeric', 'min:0'],
            'quality_status' => ['nullable', 'in:excellent,good,acceptable,poor,rejected'],
            'quality_notes' => ['nullable', 'string'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'bin_location' => ['nullable', 'string', 'max:255'],
            'batch_number' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
