<?php

namespace App\Actions\PurchaseOrder;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrderAction
{
    public function execute(array $data, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            // Set defaults
            $data['status'] = $data['status'] ?? PurchaseOrderStatus::DRAFT;
            $data['created_by'] = $userId;

            // Create purchase order
            $purchaseOrder = PurchaseOrder::create($data);
            $products = Product::query()
                ->with('variations')
                ->whereIn('id', collect($items)->pluck('product_id')->filter())
                ->get()
                ->keyBy('id');

            // Create items
            foreach ($items as $itemData) {
                $itemData['purchase_order_id'] = $purchaseOrder->id;
                $itemData['created_by'] = $userId;
                $itemData = $this->prepareItemData($itemData, $products->get($itemData['product_id']));

                PurchaseOrderItem::create($itemData);
            }

            // Calculate totals
            $purchaseOrder->calculateTotals();

            return $purchaseOrder->fresh(['items']);
        });
    }

    private function prepareItemData(array $itemData, ?Product $product): array
    {
        if ($product) {
            $itemData['sku'] = $itemData['sku'] ?? $product->sku;
            $itemData['product_name'] = $itemData['product_name'] ?? $product->name;
            $itemData['unit'] = $itemData['unit'] ?? $product->unit ?? 'pcs';
        }

        if ($product && ! empty($itemData['product_variation_id'])) {
            $variation = $product->variations->firstWhere('id', (int) $itemData['product_variation_id']);

            if ($variation) {
                $itemData['sku'] = $variation->sku ?: $itemData['sku'];
                $itemData['variation_attributes'] = [
                    'name' => $variation->name,
                    'attributes' => $variation->attributes ?? [],
                ];
            }
        }

        $itemData['discount_amount'] = $itemData['discount_amount'] ?? 0;
        $itemData['discount_percent'] = $itemData['discount_percent'] ?? 0;
        $itemData['tax_rate'] = $itemData['tax_rate'] ?? 0;
        $itemData['tax_amount'] = $itemData['tax_amount'] ?? 0;
        $itemData['quantity_received'] = $itemData['quantity_received'] ?? 0;

        return $itemData;
    }
}
