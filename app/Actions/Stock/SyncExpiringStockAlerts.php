<?php

namespace App\Actions\Stock;

use App\Enums\AlertCategory;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\Business;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\Shop;
use App\Support\Stock\Expiry;
use Illuminate\Support\Carbon;

/**
 * Expiring-stock alerts for one shop (stage 11c, step 2), raised from the expiry touch:
 *
 *  - a batch with stock left that expires within the business's window (`settings.stock.expiring_alert_days`,
 *    30 by default) raises one STOCK_EXPIRING alert;
 *  - once it has expired, the same alert becomes STOCK_EXPIRED, until the batch is written off;
 *  - an alert resolves itself when its batch has no stock left (sold, written off or released elsewhere), or
 *    no longer falls in the window.
 *
 * One alert per batch, keyed `expiring:{shop}:{batch}` in its data. A system run: no user is named. Quarantined
 * batches are not alerted (they are not sellable and are awaiting a decision), and a released batch of a
 * non-medical product is exempt from expiry, as SellableStock judges it.
 */
class SyncExpiringStockAlerts
{
    public function execute(int $shopId): int
    {
        $shop = Shop::query()->find($shopId);
        if ($shop === null) {
            return 0;
        }

        $days = max(1, (int) (Business::query()->find($shop->business_id)?->settings['stock']['expiring_alert_days'] ?? 30));
        $boundary = Expiry::firstSellableDate($shop);
        $windowEnd = $boundary->addDays($days)->toDateString();

        $batches = CostLayer::query()
            ->join('products', 'products.id', '=', 'cost_layers.product_id')
            ->where('cost_layers.shop_id', $shop->id)
            ->where('cost_layers.remaining_quantity', '>', 0)
            ->whereNotNull('cost_layers.expiry_date')
            ->where('cost_layers.expiry_date', '<=', $windowEnd)
            ->whereNull('cost_layers.quarantine_status')
            ->where(fn ($query) => $query->whereNull('cost_layers.stock_release_id')->orWhere('products.is_medical', true))
            ->get(['cost_layers.id', 'cost_layers.product_id', 'cost_layers.batch_number', 'cost_layers.expiry_date', 'cost_layers.remaining_quantity']);

        $open = Alert::query()
            ->where('shop_id', $shop->id)
            ->where('alertable_type', CostLayer::class)
            ->whereIn('type', [AlertType::STOCK_EXPIRING->value, AlertType::STOCK_EXPIRED->value])
            ->where('is_resolved', false)
            ->get()
            ->keyBy('alertable_id');

        $raised = 0;
        $wanted = [];

        foreach ($batches as $batch) {
            $wanted[$batch->id] = true;
            $expiry = Carbon::parse($batch->expiry_date);
            $type = $expiry->lt($boundary) ? AlertType::STOCK_EXPIRED : AlertType::STOCK_EXPIRING;
            $existing = $open->get($batch->id);

            if ($existing === null) {
                Alert::query()->create($this->attributes($shop, $batch->id, (int) $batch->product_id, $batch->batch_number, $expiry, $type, (float) $batch->remaining_quantity));
                $raised++;
            } elseif ((string) $existing->getRawOriginal('type') !== $type->value) {
                $existing->update($this->attributes($shop, $batch->id, (int) $batch->product_id, $batch->batch_number, $expiry, $type, (float) $batch->remaining_quantity));
            }
        }

        // The batch is gone from the window, sold out, or written off: the alert clears itself.
        foreach ($open as $layerId => $alert) {
            if (! isset($wanted[$layerId])) {
                $alert->update([
                    'is_resolved' => true,
                    'resolved_at' => now(),
                    'resolution_notes' => __('The batch has no stock left to sell, or no longer expires within the window.'),
                ]);
            }
        }

        return $raised;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(Shop $shop, int $layerId, int $productId, ?string $batchNumber, Carbon $expiry, AlertType $type, float $remaining): array
    {
        $product = Product::query()->find($productId, ['id', 'name']);
        $batch = $batchNumber ? __('batch :number', ['number' => $batchNumber]) : __('a batch');
        $expired = $type === AlertType::STOCK_EXPIRED;

        return [
            'shop_id' => $shop->id,
            'title' => $expired ? __('Expired stock: :product', ['product' => $product?->name ?? '—']) : __('Stock expiring soon: :product', ['product' => $product?->name ?? '—']),
            'message' => $expired
                ? __(':batch at :shop expired on :date with :quantity left. It is not sold; write it off.', ['batch' => ucfirst($batch), 'shop' => $shop->name, 'date' => $expiry->toDateString(), 'quantity' => rtrim(rtrim(number_format($remaining, 3, '.', ''), '0'), '.')])
                : __(':batch at :shop expires on :date with :quantity left.', ['batch' => ucfirst($batch), 'shop' => $shop->name, 'date' => $expiry->toDateString(), 'quantity' => rtrim(rtrim(number_format($remaining, 3, '.', ''), '0'), '.')]),
            'type' => $type->value,
            'severity' => ($expired ? AlertType::STOCK_EXPIRED : AlertType::STOCK_EXPIRING)->defaultSeverity()->value,
            'category' => AlertCategory::INVENTORY->value,
            'alertable_type' => CostLayer::class,
            'alertable_id' => $layerId,
            'is_read' => false,
            'is_resolved' => false,
            'created_by' => null,
            'data' => ['dedupe_key' => "expiring:{$shop->id}:{$layerId}", 'source' => 'system', 'product_id' => $productId],
        ];
    }
}
