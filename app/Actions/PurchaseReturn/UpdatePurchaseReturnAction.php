<?php

namespace App\Actions\PurchaseReturn;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\ReturnItem;
use Illuminate\Support\Facades\DB;

class UpdatePurchaseReturnAction
{
    public function __construct(private CreatePurchaseReturnAction $createPurchaseReturnAction) {}

    public function execute(PurchaseReturn $purchaseReturn, array $data, ?int $userId = null): PurchaseReturn
    {
        if (! $purchaseReturn->canEdit()) {
            throw new \Exception('Only draft or pending supplier returns can be updated.');
        }

        return DB::transaction(function () use ($purchaseReturn, $data, $userId): PurchaseReturn {
            $oldReturnItemIds = $purchaseReturn->items()
                ->whereNotNull('return_item_id')
                ->pluck('return_item_id');

            $purchaseReturn->update([
                'purchase_order_id' => $data['purchase_order_id'] ?? $purchaseReturn->purchase_order_id,
                'supplier_id' => $data['supplier_id'],
                'shop_id' => $data['shop_id'],
                'sale_return_id' => $data['sale_return_id'] ?? $purchaseReturn->sale_return_id,
                'status' => $data['status'] ?? $purchaseReturn->status,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'supplier_credit_amount' => $data['supplier_credit_amount'] ?? 0,
                'supplier_credit_reference' => $data['supplier_credit_reference'] ?? null,
                'shipment_reference' => $data['shipment_reference'] ?? null,
            ]);

            $purchaseReturn->items()->delete();

            $totalAmount = $this->createPurchaseReturnAction->syncItems($purchaseReturn, $data['items'], $userId);
            $purchaseReturn->update(['total_amount' => $totalAmount]);

            $newReturnItemIds = collect($data['items'])->pluck('return_item_id')->filter();
            $removedReturnItemIds = $oldReturnItemIds->diff($newReturnItemIds);

            if ($removedReturnItemIds->isNotEmpty()) {
                $stillLinkedReturnItemIds = PurchaseReturnItem::query()
                    ->whereIn('return_item_id', $removedReturnItemIds)
                    ->pluck('return_item_id');

                ReturnItem::query()
                    ->whereIn('id', $removedReturnItemIds->diff($stillLinkedReturnItemIds))
                    ->update([
                        'return_to_supplier' => false,
                        'return_to_supplier_at' => null,
                        'return_to_supplier_notes' => null,
                    ]);
            }

            return $purchaseReturn->fresh()->load($this->createPurchaseReturnAction->relations());
        });
    }
}
