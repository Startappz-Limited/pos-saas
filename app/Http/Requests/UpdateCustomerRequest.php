<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCustomerRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'customer_type' => ['sometimes', 'required', Rule::in(['retail', 'wholesale'])],
            'allow_credit' => ['boolean'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'allow_credit' => $this->boolean('allow_credit'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('allow_credit')) {
                return;
            }

            $customerType = $this->input('customer_type');
            $customer = $this->route('customer');

            if ($customerType === null && $customer instanceof Customer) {
                $customerType = $customer->customer_type;
            }

            if ($customerType !== 'wholesale') {
                $validator->errors()->add('allow_credit', 'Credit is only available for wholesale customers.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The customer name is required.',
            'email.email' => 'Please provide a valid email address.',
            'customer_type.in' => 'Customer type must be either retail or wholesale.',
            'credit_limit.numeric' => 'Credit limit must be a valid number.',
            'credit_limit.min' => 'Credit limit cannot be negative.',
        ];
    }
}
