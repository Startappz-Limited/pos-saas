<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\PurchaseOrderItem;
use App\Support\Stock\Expiry;
use App\Support\Stock\IntakeQuantities;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStockIntakeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('stock-intake.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'purchase_order_item_id' => ['nullable', 'exists:purchase_order_items,id'],
            'product_id' => ['required_without:purchase_order_item_id', 'nullable', 'exists:products,id'],
            'product_variation_id' => ['nullable', 'exists:product_variations,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'shop_id' => ['required_without:purchase_order_item_id', 'nullable', 'exists:shops,id'],
            // The ledger holds three decimals, so the smallest receivable is 0.001; the line's own unit precision is
            // checked in withValidator() (a whole-unit product refuses a fraction).
            'quantity_received' => ['required', 'numeric', 'min:0.001'],
            'quantity_accepted' => ['required', 'numeric', 'min:0'],
            'quantity_rejected' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'quality_notes' => ['nullable', 'string'],
            // A batch's number and expiry (stage 11, step 5); expiry accepts a full date or a
            // month ("10/2026"), normalised in prepareForValidation().
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * A month-only expiry ("10/2026") becomes its last day, before the `date` rule sees it.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('expiry_date')) {
            $this->merge(['expiry_date' => Expiry::parse($this->input('expiry_date'))]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $this->addErrors($validator, IntakeQuantities::errors($this->resolveVariation(), $this->only(IntakeQuantities::FIELDS)));

            if ($this->purchase_order_item_id) {
                $item = PurchaseOrderItem::find($this->purchase_order_item_id);

                if ($item && $this->quantity_received > $item->quantity_remaining) {
                    $validator->errors()->add(
                        'quantity_received',
                        "Quantity received cannot exceed the remaining quantity of {$item->quantity_remaining} for this PO item."
                    );
                }
            }

            // A variation that tracks batches needs both, on any line that receives stock
            // (stage 11, step 5).
            $variation = $this->resolveVariation();
            if ($variation !== null && $variation->track_batches && (! $this->filled('batch_number') || ! $this->filled('expiry_date'))) {
                $validator->errors()->add('batch_number', 'This product tracks batches: give a batch number and expiry.');
            }
        });
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function addErrors(Validator $validator, array $errors): void
    {
        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $validator->errors()->add($field, $message);
            }
        }
    }

    private function resolveVariation(): ?ProductVariation
    {
        if ($this->purchase_order_item_id) {
            $item = PurchaseOrderItem::find($this->purchase_order_item_id);

            return $item?->product_variation_id !== null
                ? ProductVariation::find($item->product_variation_id)
                : $item?->product?->defaultVariation()->first();
        }

        if ($this->filled('product_variation_id')) {
            return ProductVariation::find($this->input('product_variation_id'));
        }

        if ($this->filled('product_id')) {
            return Product::find($this->input('product_id'))?->defaultVariation()->first();
        }

        return null;
    }
}
