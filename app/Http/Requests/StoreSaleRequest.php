<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Rules\KraPin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'source_id' => ['required', 'exists:sale_sources,id'],
            'delivery_location' => ['required', 'string', 'max:1000'],
            'walk_in_customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'walk_in_customer_email' => ['nullable', 'email', 'max:255'],
            'walk_in_customer_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:20'],
            'delivery_company_id' => ['nullable', 'exists:delivery_companies,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            // Only honoured for shops that are not VAT registered. For a
            // registered shop the server computes VAT from the lines and
            // ignores whatever is posted here — tax is not a client decision.
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'customer_tax_pin' => ['nullable', 'string', 'max:32', new KraPin],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'packaging_fee' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'other_expenses' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'expense_notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'in:cash,card,bank_transfer,mobile_money,credit'],
            'is_cod' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateCreditCustomer($validator);

            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $variationId = $item['variation_id'] ?? null;

                if (! $productId) {
                    continue;
                }

                $product = Product::find($productId);

                if (! $product) {
                    continue;
                }

                // Require variation_id when the product has variations
                if ($product->has_variations && empty($variationId)) {
                    $validator->errors()->add(
                        "items.{$index}.variation_id",
                        'A variation must be selected for this product.'
                    );
                }

                // Validate that variation belongs to the selected product
                if ($variationId) {
                    $variation = ProductVariation::where('id', $variationId)
                        ->where('product_id', $productId)
                        ->first();

                    if (! $variation) {
                        $validator->errors()->add(
                            "items.{$index}.variation_id",
                            'The selected variation does not belong to this product.'
                        );
                    }
                }
            }
        });
    }

    private function validateCreditCustomer(Validator $validator): void
    {
        if ($this->input('payment_method') !== 'credit') {
            return;
        }

        $customerId = $this->input('customer_id');

        if (! $customerId) {
            $validator->errors()->add('customer_id', 'A customer is required for credit sales.');

            return;
        }

        $customer = Customer::query()->find($customerId);

        if ($customer && $customer->customer_type !== 'wholesale') {
            $validator->errors()->add('customer_id', 'Credit sales are only available for wholesale customers.');
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'discount_amount' => $this->discount_amount ?? 0,
            'tax_amount' => $this->tax_amount ?? 0,
            'delivery_fee' => $this->delivery_fee ?? 0,
            'packaging_fee' => $this->packaging_fee ?? 0,
            'other_expenses' => $this->other_expenses ?? 0,
            'is_cod' => $this->has('is_cod') && $this->is_cod,
        ]);
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'source_id' => 'sale source',
            'shop_id' => 'shop',
            'delivery_location' => 'delivery location',
            'walk_in_customer_name' => 'customer name',
            'walk_in_customer_email' => 'customer email',
            'walk_in_customer_phone' => 'customer phone',
            'delivery_company_id' => 'delivery company',
            'items.*.product_id' => 'product',
            'items.*.variation_id' => 'variation',
            'items.*.quantity' => 'quantity',
            'items.*.price' => 'price',
        ];
    }
}
