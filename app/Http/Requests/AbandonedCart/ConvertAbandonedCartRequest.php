<?php

namespace App\Http\Requests\AbandonedCart;

use Illuminate\Foundation\Http\FormRequest;

class ConvertAbandonedCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Credit is not offered here: a credit sale needs the credit-account
     * checks of the till, and a recovered website cart is a cash-like sale.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'in:cash,card,bank_transfer,mobile_money'],
            'is_cod' => ['nullable', 'boolean'],
            'register_id' => ['nullable', 'integer', 'exists:cash_registers,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'source_id' => ['nullable', 'integer', 'exists:sale_sources,id'],
            'delivery_location' => ['nullable', 'string', 'max:1000'],
            'delivery_company_id' => ['nullable', 'integer', 'exists:delivery_companies,id'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_cod' => $this->boolean('is_cod'),
        ]);
    }
}
