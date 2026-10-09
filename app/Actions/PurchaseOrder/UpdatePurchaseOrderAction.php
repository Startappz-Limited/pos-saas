<?php

namespace App\Actions\PurchaseOrder;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;

class UpdatePurchaseOrderAction
{
    public function execute(PurchaseOrder $purchaseOrder, array $data, ?int $userId = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $userId) {
            $items = $data['items'] ?? null;
            unset($data['items']);

            // Update purchase order
            $data['updated_by'] = $userId;
            $purchaseOrder->update($data);

            $products = $items === null
                ? collect()
                : Product::query()
                    ->with('variations')
                    ->whereIn('id', collect($items)->pluck('product_id')->filter())
                    ->get()
                    ->keyBy('id');

            // Update items if provided
            if ($items !== null) {
                // Get existing item IDs
                $existingItemIds = $purchaseOrder->items->pluck('id')->toArray();
                $updatedItemIds = [];

                foreach ($items as $itemData) {
                    if (isset($itemData['id'])) {
                        // Update existing item
                        $item = PurchaseOrderItem::find($itemData['id']);
                        if ($item && $item->purchase_order_id === $purchaseOrder->id) {
                            $itemData['updated_by'] = $userId;
                            $itemData = $this->prepareItemData($itemData, $products->get($itemData['product_id']));
                            $item->update($itemData);
                            $updatedItemIds[] = $item->id;
                        }
                    } else {
                        // Create new item
                        $itemData['purchase_order_id'] = $purchaseOrder->id;
                        $itemData['created_by'] = $userId;
                        $itemData = $this->prepareItemData($itemData, $products->get($itemData['product_id']));
                        $item = PurchaseOrderItem::create($itemData);
                        $updatedItemIds[] = $item->id;
                    }
                }

                // Delete removed items
                $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
                PurchaseOrderItem::whereIn('id', $itemsToDelete)->delete();
            }

            // Recalculate totals
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
