<?php

namespace App\Http\Requests;

use App\Enums\AdjustmentReason;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A stock adjustment, created or edited (stage 10, step 1g). Each line names the
 * variation it moves (required for a product with variations; the default one
 * otherwise) and a signed change: `+` adds stock, `−` removes it, agreeing with
 * the reason's direction. "Returned to supplier" is no longer offered: that is a
 * purchase return.
 */
class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $adjustment = $this->route('stock_adjustment') ?? $this->route('stockAdjustment');

        return $adjustment instanceof StockAdjustment
            ? ($this->user()?->can('update', $adjustment) ?? false)
            : ($this->user()?->can('create', StockAdjustment::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = ! ($this->route('stock_adjustment') ?? $this->route('stockAdjustment')) instanceof StockAdjustment;

        return [
            'shop_id' => $creating ? ['required', 'exists:shops,id'] : ['prohibited'],
            'reason' => ['required', Rule::in(array_map(fn (AdjustmentReason $reason): string => $reason->value, AdjustmentReason::offered()))],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.quantity_change' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'items.*.item_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.in' => __('Choose a reason. Stock going back to a supplier is a purchase return.'),
            'items.*.quantity_change.not_in' => __('Enter how much stock to add (+) or remove (−).'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $reason = AdjustmentReason::from((string) $this->input('reason'));
            $products = Product::query()->with('variations:id,product_id,is_default')
                ->whereIn('id', collect($this->input('items', []))->pluck('product_id'))->get()->keyBy('id');

            foreach ((array) $this->input('items', []) as $index => $item) {
                $change = (int) $item['quantity_change'];

                if (($reason->direction() === 'increase' && $change < 0) || ($reason->direction() === 'decrease' && $change > 0)) {
                    $validator->errors()->add("items.{$index}.quantity_change", $reason->direction() === 'increase'
                        ? __('":reason" adds stock: enter a positive number.', ['reason' => $reason->label()])
                        : __('":reason" removes stock: enter a negative number.', ['reason' => $reason->label()]));
                }

                $product = $products[$item['product_id']] ?? null;
                $variationId = $item['variation_id'] ?? null;
                $named = $product?->variations->where('is_default', false);

                if ($product !== null && $named->isNotEmpty() && $variationId === null) {
                    $validator->errors()->add("items.{$index}.variation_id", __('Choose which variation of :product.', ['product' => $product->name]));
                } elseif ($product !== null && $variationId !== null && ! $product->variations->contains('id', (int) $variationId)) {
                    $validator->errors()->add("items.{$index}.variation_id", __('That variation isn\'t one of :product\'s.', ['product' => $product->name]));
                }
            }
        });
    }
}
