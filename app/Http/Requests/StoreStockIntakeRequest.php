<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shop;
use App\Models\Supplier;
use App\Rules\ExistsForViewer;
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
        return $this->user()->can('stock_intakes.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['nullable', new ExistsForViewer(PurchaseOrder::class)],
            'purchase_order_item_id' => ['nullable', 'exists:purchase_order_items,id'],
            'product_id' => ['required_without:purchase_order_item_id', 'nullable', new ExistsForViewer(Product::class)],
            'product_variation_id' => ['nullable', 'exists:product_variations,id'],
            'supplier_id' => ['nullable', new ExistsForViewer(Supplier::class)],
            'shop_id' => ['required_without:purchase_order_item_id', 'nullable', new ExistsForViewer(Shop::class)],
            'quantity_received' => ['required', 'numeric', 'min:0.01'],
            'quantity_accepted' => ['required', 'numeric', 'min:0'],
            'quantity_rejected' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'quality_status' => ['nullable', 'in:passed,partial,failed'],
            'quality_notes' => ['nullable', 'string'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($this->purchase_order_item_id) {
                $item = PurchaseOrderItem::find($this->purchase_order_item_id);

                if ($item && $this->quantity_received > $item->quantity_remaining) {
                    $validator->errors()->add(
                        'quantity_received',
                        "Quantity received cannot exceed the remaining quantity of {$item->quantity_remaining} for this PO item."
                    );
                }
            }

            $accepted = (float) ($this->quantity_accepted ?? 0);
            $rejected = (float) ($this->quantity_rejected ?? 0);
            $received = (float) ($this->quantity_received ?? 0);

            if (($accepted + $rejected) > $received) {
                $validator->errors()->add(
                    'quantity_accepted',
                    'Accepted + rejected quantities cannot exceed the received quantity.'
                );
            }
        });
    }
}
