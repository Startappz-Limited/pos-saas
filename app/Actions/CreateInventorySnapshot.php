<?php

namespace App\Actions;

use App\Casts\Quantity;
use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Support\Pricing\UnitPrice;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records a shop's stock of every tracked item for one day (stage 11c, step 3): one snapshot per variation, always
 * naming its variation (a simple product's default one).
 *
 *  - quantity: the shop's stock in base units (decimal, `Quantity`), as it stands;
 *  - total value: the cost of the batches held (FIFO), summed only where the batch has a known cost;
 *  - unknown-cost quantity: the batches held with no known cost, counted apart, not as zero value;
 *  - shortfall: stock below zero (a sale that took more than was held), carried at no value;
 *  - retail value: the shop's price for the base unit (UnitPrice) times the stock held, never below zero.
 *
 * Taking it again for the same day, kind and variation replaces that row, so a day keeps one snapshot per variation.
 */
class CreateInventorySnapshot
{
    /**
     * @return Collection<int, InventorySnapshot>
     */
    public function execute(int $shopId, ?string $date = null, ?int $userId = null, string $kind = InventorySnapshot::KIND_DAILY, string $source = InventorySnapshot::SOURCE_ON_TIME): Collection
    {
        $shop = Shop::query()->find($shopId);
        if ($shop === null) {
            return collect();
        }

        $snapshotDate = $date ?? now()->toDateString();
        $products = Product::where('track_stock', true)->get()->keyBy('id');

        $rows = DB::table('shop_variation')
            ->tap(fn ($query) => app(TenantContext::class)->restrictToShops($query, 'shop_variation.shop_id'))
            ->join('product_variations', 'product_variations.id', '=', 'shop_variation.variation_id')
            ->where('shop_variation.shop_id', $shopId)
            ->whereIn('shop_variation.product_id', $products->keys())
            ->get(['shop_variation.product_id', 'shop_variation.variation_id', 'shop_variation.stock_quantity', 'product_variations.is_default']);

        // Only a variation the product's own form shows: a simple product's default one, or each of a product's variations.
        $rows = $rows->filter(fn (object $row): bool => (bool) $row->is_default === ! $products[$row->product_id]->has_variations)->values();

        $batches = $this->batchesHeld($shopId, $rows->pluck('variation_id')->unique()->all());
        $baseUnits = ProductUnit::query()
            ->whereIn('variation_id', $rows->pluck('variation_id')->unique()->all())
            ->where('is_base', true)
            ->get()
            ->keyBy('variation_id');

        return $rows->map(function (object $row) use ($shop, $batches, $baseUnits, $snapshotDate, $kind, $source, $userId): InventorySnapshot {
            $quantity = Quantity::normalize($row->stock_quantity);
            $held = $batches->get((int) $row->variation_id);
            $shortfall = bccomp($quantity, '0', 3) < 0 ? bcmul($quantity, '-1', 3) : '0.000';
            $baseUnit = $baseUnits->get((int) $row->variation_id);
            $price = $baseUnit !== null ? UnitPrice::for($shop, $baseUnit) : null;
            $inStock = bccomp($quantity, '0', 3) > 0 ? $quantity : '0.000';

            return $this->save([
                'shop_id' => $shop->id,
                'product_id' => $row->product_id,
                'variation_id' => $row->variation_id,
                'quantity_on_hand' => $quantity,
                'total_value' => round((float) ($held->value ?? 0), 2),
                'unknown_cost_quantity' => Quantity::normalize($held->unknown_quantity ?? 0),
                'shortfall_quantity' => $shortfall,
                'retail_value' => round((float) bcmul($inStock, (string) ($price ?? 0), 6), 2),
                'snapshot_date' => $snapshotDate,
                'kind' => $kind,
                'source' => $source,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        })->values();
    }

    /**
     * The batches the shop holds, per variation: the FIFO value of those with a known cost, and the quantity of
     * those with none.
     *
     * @param  list<int>  $variationIds
     * @return Collection<int, object{value: float, unknown_quantity: string}>
     */
    private function batchesHeld(int $shopId, array $variationIds): Collection
    {
        if ($variationIds === []) {
            return collect();
        }

        return DB::table('cost_layers')
            ->tap(fn ($query) => app(TenantContext::class)->restrictToShops($query, 'shop_id'))
            ->where('shop_id', $shopId)
            ->whereIn('variation_id', $variationIds)
            ->where('remaining_quantity', '>', 0)
            ->groupBy('variation_id')
            ->selectRaw('variation_id, sum(case when unit_cost is null then 0 else remaining_quantity * unit_cost end) as value, sum(case when unit_cost is null then remaining_quantity else 0 end) as unknown_quantity')
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->variation_id);
    }

    /**
     * Saves one snapshot, replacing the row for its day, kind and variation.
     *
     * @param  array<string, mixed>  $data
     */
    private function save(array $data): InventorySnapshot
    {
        // whereDate, not an exact match: the date column is compared by its day, whatever time a driver stores with it.
        $existing = InventorySnapshot::query()
            ->where('shop_id', $data['shop_id'])
            ->where('variation_id', $data['variation_id'])
            ->where('kind', $data['kind'])
            ->whereDate('snapshot_date', $data['snapshot_date'])
            ->first();

        if ($existing !== null) {
            $existing->update($data);

            return $existing;
        }

        return InventorySnapshot::create($data);
    }
}
