<?php

namespace App\Http\Requests;

use App\Casts\Quantity;
use App\Enums\AdjustmentReason;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Rules\QuantityForUnit;
use App\Support\Stock\BaseQuantity;
use App\Support\Stock\Expiry;
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
            // Signed, in the line's unit when it names one (stage 11, step 4), else in base units; its decimals
            // are checked against that unit below.
            'items.*.quantity_change' => ['required', 'numeric', 'not_in:0', 'between:-1000000,1000000'],
            'items.*.unit_id' => ['nullable', 'uuid'],
            'items.*.item_notes' => ['nullable', 'string', 'max:500'],
            // An increase line's own batch, expiry and cost; a decrease line's chosen batch
            // (stage 11, step 5).
            'items.*.cost_layer_id' => ['nullable', 'integer', 'exists:cost_layers,id'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.in' => __('Choose a reason. Stock going back to a supplier is a purchase return.'),
            'items.*.quantity_change.not_in' => __('Enter how much stock to add (+) or remove (−).'),
        ];
    }

    /**
     * A month-only expiry ("10/2026") becomes its last day, before the `date` rule sees it.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items', []);
        // A cost is entered only by someone who may set costs (stage 10, step 1f); from anyone else it
        // is ignored, so their increase is received at an unknown cost, never one they chose.
        $mayEnterCost = $this->user()?->can('products.set-cost') ?? false;
        foreach ($items as $index => $item) {
            if (! empty($item['expiry_date'])) {
                $items[$index]['expiry_date'] = Expiry::parse($item['expiry_date']);
            }
            if (! $mayEnterCost) {
                unset($items[$index]['unit_cost']);
            }
        }
        $this->merge(['items' => $items]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // The shop the lines move stock at: the adjustment's own on edit, else the shop picked on create.
            $adjustment = $this->route('stock_adjustment') ?? $this->route('stockAdjustment');
            $shopId = $adjustment instanceof StockAdjustment ? $adjustment->shop_id : $this->input('shop_id');

            $reason = AdjustmentReason::from((string) $this->input('reason'));
            $products = Product::query()->with('variations:id,product_id,is_default,track_batches')
                ->whereIn('id', collect($this->input('items', []))->pluck('product_id'))->get()->keyBy('id');
            $needsNamedBatch = $reason->requiresNamedBatch();

            foreach ((array) $this->input('items', []) as $index => $item) {
                $change = (float) $item['quantity_change'];

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

                if ($product === null) {
                    continue;
                }

                $variation = $variationId !== null
                    ? $product->variations->firstWhere('id', (int) $variationId)
                    : $product->defaultVariation()->first();

                if ($variation !== null) {
                    // The unit the quantity is entered in (its own, else the base unit), and its decimals.
                    $unit = ! empty($item['unit_id']) ? ProductUnit::resolveFor($variation, (string) $item['unit_id']) : null;

                    if (! empty($item['unit_id']) && $unit === null) {
                        $validator->errors()->add("items.{$index}.unit_id", __('That unit isn\'t one of this product\'s units.'));
                    }

                    $precision = $unit?->precision ?? $variation->baseUnit()->value('precision') ?? 0;
                    (new QuantityForUnit((int) $precision))->validate("items.{$index}.quantity_change", ltrim((string) $item['quantity_change'], '-+'), function (string $message) use ($validator, $index): void {
                        $validator->errors()->add("items.{$index}.quantity_change", $message);
                    });

                    // A line in a unit must move whole base units' worth exactly, or it would move no stock (BaseQuantity).
                    if ($unit !== null && ! $validator->errors()->has("items.{$index}.quantity_change")
                        && BaseQuantity::of(Quantity::normalize($item['quantity_change']), Quantity::normalize($unit->factor), (int) ($variation->baseUnit()->value('precision') ?? 3)) === null) {
                        $validator->errors()->add("items.{$index}.quantity_change", __('That quantity is too small to move in this unit: it makes less than one base unit, or a fraction of one.'));
                    }
                }

                // A batch the line names must be one of this shop's batches for this variation, and hold enough
                // for a decrease (the ledger checks again at approval, when the batch may have been used).
                if (! empty($item['cost_layer_id'])) {
                    $layer = CostLayer::query()->find($item['cost_layer_id']);

                    if ($layer === null || $variation === null || (int) $layer->shop_id !== (int) $shopId || (int) $layer->variation_id !== (int) $variation->id) {
                        $validator->errors()->add("items.{$index}.cost_layer_id", __('That batch isn\'t one of this product\'s batches at this shop.'));
                    } elseif ($change < 0 && ! $validator->errors()->has("items.{$index}.quantity_change")) {
                        // The batch balance is in base units, so the line's quantity is converted to base units first:
                        // one crate of 24 takes 24 bottles, and half a bottle's unit takes half of each entered.
                        $lineUnit = ! empty($item['unit_id']) ? ProductUnit::resolveFor($variation, (string) $item['unit_id']) : null;
                        $baseTaken = BaseQuantity::of(Quantity::normalize(ltrim((string) $item['quantity_change'], '-+')), Quantity::normalize($lineUnit?->factor ?? '1'));
                        $available = Quantity::normalize($layer->remaining_quantity);

                        if ($baseTaken !== null && bccomp($available, $baseTaken, 3) < 0) {
                            $validator->errors()->add("items.{$index}.cost_layer_id", __('That batch has only :available base units left.', ['available' => $available]));
                        }
                    }
                }

                if ($variation === null || ! $variation->track_batches) {
                    continue;
                }

                if ($change > 0 && (empty($item['batch_number']) || empty($item['expiry_date']))) {
                    $validator->errors()->add("items.{$index}.batch_number", __('This product tracks batches: give a batch number and expiry.'));
                } elseif ($change < 0 && $needsNamedBatch && empty($item['cost_layer_id'])) {
                    $validator->errors()->add("items.{$index}.cost_layer_id", __('This product tracks batches: choose which batch :reason takes from.', ['reason' => $reason->label()]));
                }
            }
        });
    }
}
