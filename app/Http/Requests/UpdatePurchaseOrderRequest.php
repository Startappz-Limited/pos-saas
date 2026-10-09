<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Rules\ExistsForViewer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('purchase_orders.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['sometimes', 'required', new ExistsForViewer(Supplier::class)],
            'shop_id' => ['sometimes', 'required', new ExistsForViewer(Shop::class)],
            'order_date' => ['sometimes', 'required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after:order_date'],
            'actual_delivery_date' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'payment_due_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'terms_and_conditions' => ['nullable', 'string'],
            'items' => ['sometimes', 'array'],
            'items.*.id' => ['nullable', 'exists:purchase_order_items,id'],
            'items.*.product_id' => ['required', new ExistsForViewer(Product::class)],
            'items.*.product_variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.quantity_ordered' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateProductsBelongToSelectedShop($validator);
            },
        ];
    }

    private function validateProductsBelongToSelectedShop(Validator $validator): void
    {
        $shopId = $this->input('shop_id');
        $purchaseOrder = $this->route('purchase_order') ?? $this->route('purchaseOrder');

        if (blank($shopId) && $purchaseOrder instanceof PurchaseOrder) {
            $shopId = $purchaseOrder->shop_id;
        }

        $items = $this->input('items', []);

        if (blank($shopId) || empty($items)) {
            return;
        }

        $productIds = collect($items)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($productId) => (int) $productId)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return;
        }

        $assignedProductIds = Product::query()
            ->whereIn('id', $productIds)
            ->whereHas('shops', fn ($query) => $query->whereKey($shopId))
            ->pluck('id')
            ->map(fn ($productId) => (int) $productId);

        $invalidProductIds = $productIds->diff($assignedProductIds);

        foreach ($items as $index => $item) {
            if (isset($item['product_id']) && $invalidProductIds->contains((int) $item['product_id'])) {
                $validator->errors()->add("items.{$index}.product_id", 'Selected product is not assigned to the selected shop.');
            }
        }
    }
}
