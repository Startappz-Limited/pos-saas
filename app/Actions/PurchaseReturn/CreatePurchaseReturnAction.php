<?php

namespace App\Actions\PurchaseReturn;

use App\Enums\PurchaseReturnStatus;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\ReturnItem;
use App\Models\StockIntake;
use Illuminate\Support\Facades\DB;

class CreatePurchaseReturnAction
{
    public function execute(array $data, ?int $userId = null): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $userId): PurchaseReturn {
            $headerData = $this->resolveHeaderData($data);

            $purchaseReturn = PurchaseReturn::create([
                'purchase_order_id' => $headerData['purchase_order_id'],
                'supplier_id' => $data['supplier_id'],
                'shop_id' => $data['shop_id'],
                'sale_return_id' => $headerData['sale_return_id'],
                'status' => $data['status'] ?? PurchaseReturnStatus::PENDING,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'supplier_credit_amount' => $data['supplier_credit_amount'] ?? 0,
                'supplier_credit_reference' => $data['supplier_credit_reference'] ?? null,
                'shipment_reference' => $data['shipment_reference'] ?? null,
                'requested_by' => $userId,
            ]);

            $totalAmount = $this->syncItems($purchaseReturn, $data['items'], $userId);

            $purchaseReturn->update(['total_amount' => $totalAmount]);

            return $purchaseReturn->fresh()->load($this->relations());
        });
    }

    public function syncItems(PurchaseReturn $purchaseReturn, array $items, ?int $userId = null): float
    {
        $stockIntakes = StockIntake::query()
            ->with(['purchaseOrderItem'])
            ->whereIn('id', collect($items)->pluck('stock_intake_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $returnItems = ReturnItem::query()
            ->with(['saleItem', 'product'])
            ->whereIn('id', collect($items)->pluck('return_item_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $totalAmount = 0;

        foreach ($items as $itemData) {
            $stockIntake = $stockIntakes->get($itemData['stock_intake_id'] ?? null);
            $returnItem = $returnItems->get($itemData['return_item_id'] ?? null);
            $quantity = (int) $itemData['quantity'];
            $unitCost = $this->resolveUnitCost($itemData, $stockIntake, $returnItem);
            $lineTotal = $quantity * $unitCost;
            $totalAmount += $lineTotal;

            PurchaseReturnItem::create([
                'purchase_return_id' => $purchaseReturn->id,
                'purchase_order_item_id' => $itemData['purchase_order_item_id'] ?? $stockIntake?->purchase_order_item_id,
                'stock_intake_id' => $stockIntake?->id,
                'return_item_id' => $returnItem?->id,
                'product_id' => $itemData['product_id'],
                'product_variation_id' => $itemData['product_variation_id'] ?? $stockIntake?->product_variation_id ?? $returnItem?->saleItem?->variation_id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_total' => $lineTotal,
                'condition' => $itemData['condition'] ?? $returnItem?->condition,
                'notes' => $itemData['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if ($returnItem) {
                $returnItem->update([
                    'return_to_supplier' => true,
                    'return_to_supplier_at' => $returnItem->return_to_supplier_at ?? now(),
                    'return_to_supplier_notes' => $itemData['notes'] ?? $returnItem->return_to_supplier_notes,
                ]);
            }
        }

        return $totalAmount;
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return [
            'purchaseOrder',
            'supplier',
            'shop',
            'saleReturn',
            'items.product',
            'items.productVariation',
            'items.stockIntake',
            'items.returnItem',
            'requestedBy',
            'approvedBy',
            'shippedBy',
            'completedBy',
        ];
    }

    /**
     * @return array{purchase_order_id: int|null, sale_return_id: int|null}
     */
    private function resolveHeaderData(array $data): array
    {
        $firstStockIntakeId = collect($data['items'])->pluck('stock_intake_id')->filter()->first();
        $firstReturnItemId = collect($data['items'])->pluck('return_item_id')->filter()->first();

        $stockIntake = $firstStockIntakeId ? StockIntake::query()->find($firstStockIntakeId) : null;
        $returnItem = $firstReturnItemId ? ReturnItem::query()->find($firstReturnItemId) : null;

        return [
            'purchase_order_id' => $data['purchase_order_id'] ?? $stockIntake?->purchase_order_id,
            'sale_return_id' => $data['sale_return_id'] ?? $returnItem?->return_id,
        ];
    }

    private function resolveUnitCost(array $itemData, ?StockIntake $stockIntake, ?ReturnItem $returnItem): float
    {
        if (($itemData['unit_cost'] ?? null) !== null && $itemData['unit_cost'] !== '') {
            return (float) $itemData['unit_cost'];
        }

        return (float) (
            $stockIntake?->purchaseOrderItem?->unit_cost
            ?? $returnItem?->saleItem?->unit_cost
            ?? $returnItem?->product?->cost_price
            ?? 0
        );
    }
}
