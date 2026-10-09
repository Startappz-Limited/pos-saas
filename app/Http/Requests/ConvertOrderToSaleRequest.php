<?php

namespace App\Http\Requests;

use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\DeliveryCompany;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\SaleSource;
use App\Rules\ExistsForViewer;
use Illuminate\Foundation\Http\FormRequest;

class ConvertOrderToSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'register_id' => ['nullable', new ExistsForViewer(CashRegister::class)],
            'source_id' => ['required', new ExistsForViewer(SaleSource::class)],
            'customer_id' => ['nullable', new ExistsForViewer(Customer::class)],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'delivery_company_id' => ['nullable', new ExistsForViewer(DeliveryCompany::class)],
            'delivery_location' => ['nullable', 'string', 'max:1000'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['nullable', 'exists:ecommerce_order_items,id'],
            'items.*.product_id' => ['required', new ExistsForViewer(Product::class)],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $order = $this->route('order');
            $orderItemIds = $order?->items()->pluck('id')->all() ?? [];
            $seenOrderItemIds = [];

            foreach ($this->input('items', []) as $index => $item) {
                $orderItemId = $item['order_item_id'] ?? null;
                $productId = $item['product_id'] ?? null;
                $variationId = $item['variation_id'] ?? null;

                if ($orderItemId) {
                    if (! in_array((int) $orderItemId, $orderItemIds, true)) {
                        $validator->errors()->add("items.{$index}.order_item_id", 'The selected order item does not belong to this order.');
                    }

                    if (in_array((int) $orderItemId, $seenOrderItemIds, true)) {
                        $validator->errors()->add("items.{$index}.order_item_id", 'The same order item cannot be submitted more than once.');
                    }

                    $seenOrderItemIds[] = (int) $orderItemId;
                }

                if (! $productId) {
                    continue;
                }

                $product = Product::find($productId);
                if (! $product) {
                    continue;
                }

                if ($product->has_variations && empty($variationId)) {
                    $validator->errors()->add("items.{$index}.variation_id", 'A variation must be selected for this product.');
                }

                if ($variationId) {
                    $variationExists = ProductVariation::where('id', $variationId)
                        ->where('product_id', $productId)
                        ->exists();

                    if (! $variationExists) {
                        $validator->errors()->add("items.{$index}.variation_id", 'The selected variation does not belong to this product.');
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'register_id.required' => 'Please select a cash register.',
            'source_id.required' => 'Please select a sale source.',
            'items.required' => 'At least one item is required.',
            'items.*.product_id.required' => 'Each item must have a matched product.',
            'items.*.quantity.required' => 'Each item must have a quantity.',
            'items.*.unit_price.required' => 'Each item must have a unit price.',
        ];
    }
}
