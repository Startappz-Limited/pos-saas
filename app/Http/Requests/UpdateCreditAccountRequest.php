<?php

namespace App\Http\Requests;

use App\Enums\CreditAccountStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreditAccountRequest extends FormRequest
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
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'status' => ['required', Rule::enum(CreditAccountStatus::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_terms_days' => $this->input('payment_terms_days', 30),
            'grace_period_days' => $this->input('grace_period_days', 7),
            'status' => $this->input('status', CreditAccountStatus::ACTIVE->value),
        ]);
    }
}
