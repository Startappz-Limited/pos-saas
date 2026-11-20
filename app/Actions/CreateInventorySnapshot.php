<?php

namespace App\Actions;

use App\Casts\Quantity;
use App\Models\CostLayer;
use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Support\Pricing\UnitPrice;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records a shop's stock of every tracked item for one day (stage 11c, steps 3 and 4): one snapshot per variation,
 * always naming its variation (a simple product's default one).
 *
 *  - quantity: the shop's stock in base units (decimal, `Quantity`);
 *  - total value: the cost of the batches held (FIFO), summed only where the batch has a known cost;
 *  - unknown-cost quantity: the batches held with no known cost, counted apart, not as zero value;
 *  - shortfall: stock below zero (a sale that took more than was held), carried at no value;
 *  - retail value: the shop's price for the base unit (UnitPrice) times the stock held, never below zero.
 *
 * With $at, the figures are those the shop held at that instant (step 4): the current stock and batches, with every
 * change made after it undone from the ledger (see stateAt()). Without it, they are the current figures.
 *
 * Taking it again for the same day, kind and variation replaces that row, so a day keeps one snapshot per variation.
 */
class CreateInventorySnapshot
{
    /**
     * @return Collection<int, InventorySnapshot>
     */
    public function execute(int $shopId, ?string $date = null, ?int $userId = null, string $kind = InventorySnapshot::KIND_DAILY, string $source = InventorySnapshot::SOURCE_ON_TIME, ?CarbonInterface $at = null): Collection
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

        return $rows->map(function (object $row) use ($shop, $batches, $baseUnits, $snapshotDate, $kind, $source, $userId, $at): InventorySnapshot {
            $variationId = (int) $row->variation_id;
            $current = Quantity::normalize($row->stock_quantity);
            $held = $batches->get($variationId);

            // A figure the ledger can't rebuild exactly is taken as the current one, and marked late (step 4).
            $state = $at !== null ? $this->stateAt($shop, $variationId, $at, $current) : null;
            $rowSource = $at !== null && $state === null ? InventorySnapshot::SOURCE_LATE : $source;

            $quantity = $state['quantity'] ?? $current;
            $totalValue = $state['total_value'] ?? round((float) ($held->value ?? 0), 2);
            $unknown = $state['unknown_cost_quantity'] ?? Quantity::normalize($held->unknown_quantity ?? 0);
            $shortfall = bccomp($quantity, '0', 3) < 0 ? bcmul($quantity, '-1', 3) : '0.000';

            $baseUnit = $baseUnits->get($variationId);
            $price = $baseUnit !== null ? UnitPrice::for($shop, $baseUnit) : null;
            $inStock = bccomp($quantity, '0', 3) > 0 ? $quantity : '0.000';

            return $this->save([
                'shop_id' => $shop->id,
                'product_id' => $row->product_id,
                'variation_id' => $variationId,
                'quantity_on_hand' => $quantity,
                'total_value' => $totalValue,
                'unknown_cost_quantity' => $unknown,
                'shortfall_quantity' => $shortfall,
                'retail_value' => round((float) bcmul($inStock, (string) ($price ?? 0), 6), 2),
                'snapshot_date' => $snapshotDate,
                'kind' => $kind,
                'source' => $rowSource,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        })->values();
    }

    /**
     * The stock a variation held at $at, from the ledger (stage 11c, step 4), or null when the ledger can't say exactly.
     *
     * The current figures are taken back through every change made after $at:
     *  - quantity: the current stock less each later movement's change (its figures before and after);
     *  - each batch that existed at $at: its remaining now, plus the units consumed after $at that are still consumed
     *    (the consumption time is the settlement's for a sale that waited for stock), less the units restored after
     *    $at that had been consumed before it, plus the units a quarantine release took out of it after $at.
     *
     * Null (not rebuildable, so the caller takes the current figures as late) when a later addition has no batch
     * record (movements from before stage 11c, step 4), or when the batches don't add up to the stock (a change the
     * ledger did not record). The batches then must sum to the stock held (none when it is below zero), so a
     * rebuilt snapshot can't disagree with its own quantity.
     *
     * @return array{quantity: string, total_value: float, unknown_cost_quantity: string, shortfall_quantity: string}|null
     */
    private function stateAt(Shop $shop, int $variationId, CarbonInterface $at, string $current): ?array
    {
        $moment = CarbonImmutable::instance($at)->utc();
        $stamp = $moment->format('Y-m-d H:i:s');

        $quantity = $current;
        $restores = [];
        $quarantineOut = [];

        $later = StockMovement::query()
            ->where('shop_id', $shop->id)
            ->where('variation_id', $variationId)
            ->where('created_at', '>', $stamp)
            ->orderBy('id')
            ->get(['quantity_before', 'quantity_after', 'layers']);

        foreach ($later as $movement) {
            $change = bcsub(Quantity::normalize($movement->quantity_after), Quantity::normalize($movement->quantity_before), 3);
            $quantity = bcsub($quantity, $change, 3);

            if (bccomp($change, '0', 3) > 0 && $movement->layers === null) {
                return null;
            }

            foreach ($movement->layers ?? [] as $entry) {
                $layerId = (int) $entry['layer_id'];

                if (isset($entry['allocated_at'])) {
                    $restores[$layerId][] = ['quantity' => Quantity::normalize($entry['quantity']), 'consumed_at' => CarbonImmutable::parse($entry['allocated_at'], 'UTC')];
                }

                if (($entry['role'] ?? null) === 'quarantine_source') {
                    $quarantineOut[$layerId] = bcadd($quarantineOut[$layerId] ?? '0.000', bcmul(Quantity::normalize($entry['quantity']), '-1', 3), 3);
                }
            }
        }

        // Consumption after $at, still consumed: the units were in the batch at $at.
        $consumedAfter = [];
        $allocations = DB::table('cost_allocations')
            ->where('shop_id', $shop->id)->where('variation_id', $variationId)
            ->where('is_provisional', false)->whereNotNull('cost_layer_id')
            ->get(['cost_layer_id', 'quantity_allocated', 'allocated_at', 'settled_at']);

        foreach ($allocations as $allocation) {
            $consumedAt = CarbonImmutable::parse($allocation->settled_at ?? $allocation->allocated_at, 'UTC');
            if ($consumedAt->greaterThan($moment)) {
                $consumedAfter[(int) $allocation->cost_layer_id] = bcadd($consumedAfter[(int) $allocation->cost_layer_id] ?? '0.000', Quantity::normalize($allocation->quantity_allocated), 3);
            }
        }

        // Batches that existed at $at (recorded then, whatever their received date).
        $existing = CostLayer::query()
            ->where('shop_id', $shop->id)->where('variation_id', $variationId)
            ->where('created_at', '<=', $stamp)
            ->get(['id', 'remaining_quantity', 'unit_cost']);

        $sum = '0.000';
        $value = 0.0;
        $unknown = '0.000';

        foreach ($existing as $layer) {
            $remaining = Quantity::normalize($layer->remaining_quantity);
            $remaining = bcadd($remaining, $consumedAfter[(int) $layer->id] ?? '0.000', 3);
            $remaining = bcadd($remaining, $quarantineOut[(int) $layer->id] ?? '0.000', 3);

            foreach ($restores[(int) $layer->id] ?? [] as $restore) {
                if ($restore['consumed_at']->lessThanOrEqualTo($moment)) {
                    $remaining = bcsub($remaining, $restore['quantity'], 3);
                }
            }

            if (bccomp($remaining, '0', 3) < 0) {
                return null;
            }

            $sum = bcadd($sum, $remaining, 3);

            if ($layer->unit_cost === null) {
                $unknown = bcadd($unknown, $remaining, 3);
            } else {
                $value += (float) bcmul($remaining, (string) $layer->unit_cost, 6);
            }
        }

        $expected = bccomp($quantity, '0', 3) >= 0 ? $quantity : '0.000';
        if (bccomp($sum, $expected, 3) !== 0) {
            return null;
        }

        return [
            'quantity' => $quantity,
            'total_value' => round($value, 2),
            'unknown_cost_quantity' => $unknown,
            'shortfall_quantity' => bccomp($quantity, '0', 3) < 0 ? bcmul($quantity, '-1', 3) : '0.000',
        ];
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
