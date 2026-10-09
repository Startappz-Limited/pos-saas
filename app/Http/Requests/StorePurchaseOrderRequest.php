<?php

namespace App\Http\Requests;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('purchase_orders.create')) {
            return false;
        }

        if ($this->input('workflow') === 'approve_and_mark_ordered') {
            return $user->can('purchase_orders.full-access')
                || ($user->can('purchase_orders.approve') && $user->can('purchase_orders.update'));
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'shop_id' => ['required', 'exists:shops,id'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after:order_date'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'payment_due_date' => ['nullable', 'date'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'workflow' => ['nullable', Rule::in(['approve_and_mark_ordered'])],
            'status' => [
                'nullable',
                Rule::enum(PurchaseOrderStatus::class)->only([
                    PurchaseOrderStatus::DRAFT,
                    PurchaseOrderStatus::PENDING,
                ]),
            ],
            'notes' => ['nullable', 'string'],
            'terms_and_conditions' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
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
