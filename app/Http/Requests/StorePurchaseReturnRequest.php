<?php

namespace App\Http\Requests;

use App\Enums\PurchaseReturnReason;
use App\Enums\PurchaseReturnStatus;
use App\Enums\StockIntakeStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturnItem;
use App\Models\ReturnItem;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\StockIntake;
use App\Models\Supplier;
use App\Rules\ExistsForViewer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseReturnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('purchase_returns.create');
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->map(fn ($item) => is_array($item) ? $item : []);

        if ($items->isEmpty()) {
            return;
        }

        $stockIntakes = StockIntake::query()
            ->with(['purchaseOrderItem'])
            ->whereIn('id', $items->pluck('stock_intake_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $returnItems = ReturnItem::query()
            ->with(['saleReturn', 'saleItem', 'product'])
            ->whereIn('id', $items->pluck('return_item_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $derivedHeader = [];
        $normalisedItems = $items->map(function (array $item) use ($stockIntakes, $returnItems, &$derivedHeader): array {
            $stockIntake = $stockIntakes->get($item['stock_intake_id'] ?? null);
            $returnItem = $returnItems->get($item['return_item_id'] ?? null);

            if ($stockIntake) {
                $item['purchase_order_item_id'] = $item['purchase_order_item_id'] ?? $stockIntake->purchase_order_item_id;
                $item['product_id'] = $item['product_id'] ?? $stockIntake->product_id;
                $item['product_variation_id'] = $item['product_variation_id'] ?? $stockIntake->product_variation_id;
            }

            if ($returnItem) {
                $item['product_id'] = $item['product_id'] ?? $returnItem->product_id;
                $item['product_variation_id'] = $item['product_variation_id'] ?? $returnItem->saleItem?->variation_id;
            }

            if (($item['unit_cost'] ?? null) === null || $item['unit_cost'] === '') {
                $item['unit_cost'] = $this->resolveUnitCost($stockIntake, $returnItem);
            }

            $derivedHeader['supplier_id'] ??= $stockIntake?->supplier_id ?? $returnItem?->product?->supplier_id;
            $derivedHeader['shop_id'] ??= $stockIntake?->shop_id ?? $returnItem?->saleReturn?->shop_id;
            $derivedHeader['purchase_order_id'] ??= $stockIntake?->purchase_order_id;
            $derivedHeader['sale_return_id'] ??= $returnItem?->return_id;

            return $item;
        })->all();

        $this->merge([
            'items' => $normalisedItems,
            'supplier_id' => $this->input('supplier_id') ?: ($derivedHeader['supplier_id'] ?? null),
            'shop_id' => $this->input('shop_id') ?: ($derivedHeader['shop_id'] ?? null),
            'purchase_order_id' => $this->input('purchase_order_id') ?: ($derivedHeader['purchase_order_id'] ?? null),
            'sale_return_id' => $this->input('sale_return_id') ?: ($derivedHeader['sale_return_id'] ?? null),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['nullable', new ExistsForViewer(PurchaseOrder::class)],
            'supplier_id' => ['required', new ExistsForViewer(Supplier::class)],
            'shop_id' => ['required', new ExistsForViewer(Shop::class)],
            'sale_return_id' => ['nullable', new ExistsForViewer(SaleReturn::class)],
            'status' => ['nullable', Rule::in([
                PurchaseReturnStatus::DRAFT->value,
                PurchaseReturnStatus::PENDING->value,
            ])],
            'reason' => ['required', 'string', Rule::enum(PurchaseReturnReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'supplier_credit_amount' => ['nullable', 'numeric', 'min:0'],
            'supplier_credit_reference' => ['nullable', 'string', 'max:255'],
            'shipment_reference' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'exists:purchase_order_items,id'],
            'items.*.stock_intake_id' => ['nullable', new ExistsForViewer(StockIntake::class)],
            'items.*.return_item_id' => ['nullable', 'exists:return_items,id'],
            'items.*.product_id' => ['required', new ExistsForViewer(Product::class)],
            'items.*.product_variation_id' => ['nullable', 'exists:product_variations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.condition' => ['nullable', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = collect($this->input('items', []));

                if ($items->isEmpty()) {
                    return;
                }

                $this->validateUniqueSources($validator, $items);
                $this->validateStockIntakeItems($validator, $items);
                $this->validateCustomerReturnItems($validator, $items);
                $this->validateProductSuppliers($validator, $items);
            },
        ];
    }

    protected function currentPurchaseReturnId(): ?int
    {
        return null;
    }

    private function resolveUnitCost(?StockIntake $stockIntake, ?ReturnItem $returnItem): float
    {
        return (float) (
            $stockIntake?->purchaseOrderItem?->unit_cost
            ?? $returnItem?->saleItem?->unit_cost
            ?? $returnItem?->product?->cost_price
            ?? 0
        );
    }

    private function validateUniqueSources(Validator $validator, Collection $items): void
    {
        $seenSources = [];

        foreach ($items as $index => $item) {
            $sourceKey = null;
            $field = null;

            if (! empty($item['stock_intake_id'])) {
                $sourceKey = 'stock_intake:'.$item['stock_intake_id'];
                $field = 'stock_intake_id';
            } elseif (! empty($item['return_item_id'])) {
                $sourceKey = 'return_item:'.$item['return_item_id'];
                $field = 'return_item_id';
            }

            if (! $sourceKey || ! $field) {
                continue;
            }

            if (isset($seenSources[$sourceKey])) {
                $validator->errors()->add(
                    "items.{$index}.{$field}",
                    'This source is already selected. Increase the quantity on the existing line instead.'
                );

                continue;
            }

            $seenSources[$sourceKey] = $index;
        }
    }

    private function validateStockIntakeItems(Validator $validator, Collection $items): void
    {
        $stockIntakeIds = $items->pluck('stock_intake_id')->filter()->unique()->values();

        if ($stockIntakeIds->isEmpty()) {
            return;
        }

        $stockIntakes = StockIntake::query()
            ->with(['purchaseOrderItem'])
            ->whereIn('id', $stockIntakeIds)
            ->get()
            ->keyBy('id');

        $alreadyReturned = PurchaseReturnItem::query()
            ->whereIn('stock_intake_id', $stockIntakeIds)
            ->when($this->currentPurchaseReturnId(), fn ($query, int $id) => $query->where('purchase_return_id', '!=', $id))
            ->whereHas('purchaseReturn', fn ($query) => $query->where('status', '!=', PurchaseReturnStatus::CANCELLED->value))
            ->selectRaw('stock_intake_id, SUM(quantity) as returned_quantity')
            ->groupBy('stock_intake_id')
            ->pluck('returned_quantity', 'stock_intake_id');

        foreach ($items as $index => $item) {
            if (empty($item['stock_intake_id'])) {
                continue;
            }

            $stockIntake = $stockIntakes->get($item['stock_intake_id']);

            if (! $stockIntake) {
                continue;
            }

            if ($stockIntake->status !== StockIntakeStatus::COMPLETED) {
                $validator->errors()->add("items.{$index}.stock_intake_id", 'Only completed stock intakes can be returned to a supplier.');
            }

            if ((int) $stockIntake->supplier_id !== (int) $this->input('supplier_id')) {
                $validator->errors()->add("items.{$index}.stock_intake_id", 'The selected stock intake belongs to a different supplier.');
            }

            if ((int) $stockIntake->shop_id !== (int) $this->input('shop_id')) {
                $validator->errors()->add("items.{$index}.stock_intake_id", 'The selected stock intake belongs to a different shop.');
            }

            if ((int) $stockIntake->product_id !== (int) $item['product_id']) {
                $validator->errors()->add("items.{$index}.product_id", 'The selected product does not match the stock intake.');
            }

            $acceptedQuantity = (int) $stockIntake->quantity_accepted;
            $returnedQuantity = (int) ($alreadyReturned[$stockIntake->id] ?? 0);
            $availableQuantity = max(0, $acceptedQuantity - $returnedQuantity);

            if ((int) $item['quantity'] > $availableQuantity) {
                $validator->errors()->add("items.{$index}.quantity", "Only {$availableQuantity} units remain available to return from this stock intake.");
            }
        }
    }

    private function validateCustomerReturnItems(Validator $validator, Collection $items): void
    {
        $returnItemIds = $items->pluck('return_item_id')->filter()->unique()->values();

        if ($returnItemIds->isEmpty()) {
            return;
        }

        $returnItems = ReturnItem::query()
            ->with(['saleReturn', 'product'])
            ->whereIn('id', $returnItemIds)
            ->get()
            ->keyBy('id');

        $alreadyReturned = PurchaseReturnItem::query()
            ->whereIn('return_item_id', $returnItemIds)
            ->when($this->currentPurchaseReturnId(), fn ($query, int $id) => $query->where('purchase_return_id', '!=', $id))
            ->whereHas('purchaseReturn', fn ($query) => $query->where('status', '!=', PurchaseReturnStatus::CANCELLED->value))
            ->selectRaw('return_item_id, SUM(quantity) as returned_quantity')
            ->groupBy('return_item_id')
            ->pluck('returned_quantity', 'return_item_id');

        foreach ($items as $index => $item) {
            if (empty($item['return_item_id'])) {
                continue;
            }

            $returnItem = $returnItems->get($item['return_item_id']);

            if (! $returnItem) {
                continue;
            }

            if ((int) $returnItem->product_id !== (int) $item['product_id']) {
                $validator->errors()->add("items.{$index}.product_id", 'The selected product does not match the customer return item.');
            }

            if ((int) $returnItem->saleReturn->shop_id !== (int) $this->input('shop_id')) {
                $validator->errors()->add("items.{$index}.return_item_id", 'The selected customer return item belongs to a different shop.');
            }

            $returnedQuantity = (int) ($alreadyReturned[$returnItem->id] ?? 0);
            $availableQuantity = max(0, (int) $returnItem->quantity - $returnedQuantity);

            if ((int) $item['quantity'] > $availableQuantity) {
                $validator->errors()->add("items.{$index}.quantity", "Only {$availableQuantity} units remain available from this customer return item.");
            }
        }
    }

    private function validateProductSuppliers(Validator $validator, Collection $items): void
    {
        $productIds = $items->pluck('product_id')->filter()->unique()->values();

        if ($productIds->isEmpty()) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'supplier_id'])
            ->keyBy('id');

        foreach ($items as $index => $item) {
            if (! empty($item['stock_intake_id'])) {
                continue;
            }

            $product = $products->get($item['product_id'] ?? null);

            if (! $product || ! $product->supplier_id) {
                continue;
            }

            if ((int) $product->supplier_id !== (int) $this->input('supplier_id')) {
                $validator->errors()->add("items.{$index}.product_id", 'The selected product belongs to a different default supplier.');
            }
        }
    }
}
