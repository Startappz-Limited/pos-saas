<?php

namespace App\Services\Stock;

use App\Enums\StockMovementType;
use App\Jobs\PushShopStockJob;
use App\Models\CostAllocation;
use App\Models\CostLayer;
use App\Models\ProductVariation;
use App\Models\PurchaseReturnItem;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Observers\ProductObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only code that changes stock (stage 1b, docs/saas/fifo-analysis.md).
 *
 * Stock is held per shop and per variation in `shop_variation`. Every change
 * locks that row, moves it by a delta (never an absolute value, so two tills
 * can't overwrite each other), writes a StockMovement, and creates or consumes
 * FIFO cost layers, oldest first:
 *
 * - receive(): stock in (intake, adjustment up, restocked return, opening
 *   stock) becomes a layer, which first settles any units sold below zero,
 *   re-costing those sales;
 * - consume(): stock out (sale, write-off, purchase return) takes layers
 *   oldest first; a shortfall is allocated provisionally at the last known
 *   cost and stock goes negative (offline sales are always accepted, 4.5);
 * - release(): gives back everything one consumer took (a voided or deleted
 *   sale's line), into the batches it came from (stage 10, step 3).
 *
 * Callers have already authorised the shop: queries here filter by the given
 * shop and variation explicitly rather than through the viewer's scopes, so a
 * sale line attributed to another shop of the business, or a job with no
 * signed-in user, still moves the right stock. Call within a transaction.
 */
class StockLedger
{
    /**
     * Stock in. $unitCost NULL means unknown.
     *
     * @param  array{source_type?: string, source_id?: int|null, stock_intake_id?: int|null, reference?: Model|null, notes?: string|null, user_id?: int|null, received_at?: mixed}  $context
     */
    public function receive(int $shopId, ProductVariation $variation, int $quantity, ?float $unitCost, StockMovementType $type, array $context = []): StockChange
    {
        $quantity = abs($quantity);

        return DB::transaction(function () use ($shopId, $variation, $quantity, $unitCost, $type, $context): StockChange {
            $before = $this->lockStock($shopId, $variation);
            $this->changeStock($shopId, $variation, $quantity);

            $layerId = null;
            if ($quantity > 0) {
                $layerId = $this->createLayer($shopId, $variation, $quantity, $unitCost, $context);
                $this->settleProvisional($shopId, $variation, $layerId);
            }

            $movement = $this->recordMovement($shopId, $variation, $type, $quantity, $before, $before + $quantity, $unitCost, $context);

            return new StockChange($movement, $quantity, round($quantity * (float) $unitCost, 2), $unitCost !== null);
        });
    }

    /**
     * Stock out, costed from the layers oldest first. $preferredLayerId (a
     * purchase return's own intake) is taken first.
     *
     * @param  array{reference?: Model|null, notes?: string|null, user_id?: int|null}  $context
     */
    public function consume(int $shopId, ProductVariation $variation, int $quantity, StockMovementType $type, Model $consumer, array $context = [], ?int $preferredLayerId = null): StockChange
    {
        $quantity = abs($quantity);

        return DB::transaction(function () use ($shopId, $variation, $quantity, $type, $consumer, $context, $preferredLayerId): StockChange {
            $before = $this->lockStock($shopId, $variation);
            [$consumerType, $consumerId, $saleItemId] = $this->consumerKey($consumer);

            $layers = DB::table('cost_layers')
                ->where('shop_id', $shopId)->where('variation_id', $variation->id)->where('remaining_quantity', '>', 0)
                ->when($preferredLayerId, fn ($query) => $query->orderByRaw('case when id = ? then 0 else 1 end', [$preferredLayerId]))
                ->orderBy('received_at')->orderBy('id')
                ->lockForUpdate()
                ->get();

            $needed = $quantity;
            $totalCost = 0.0;
            $costKnown = true;

            foreach ($layers as $layer) {
                if ($needed <= 0) {
                    break;
                }
                $take = min($needed, (int) $layer->remaining_quantity);
                $cost = $layer->unit_cost === null ? null : (float) $layer->unit_cost;

                DB::table('cost_layers')->where('id', $layer->id)->update([
                    'remaining_quantity' => DB::raw('remaining_quantity - '.(int) $take),
                    'consumed_at' => $take === (int) $layer->remaining_quantity ? now() : null,
                    'updated_at' => now(),
                ]);
                $this->allocate($shopId, $variation, $layer->id, $consumerType, $consumerId, $saleItemId, $take, $cost, false);

                $totalCost += $take * (float) $cost;
                $costKnown = $costKnown && $cost !== null;
                $needed -= $take;
            }

            // Sold below zero: allocated now at the last known cost, re-costed
            // when stock arrives.
            if ($needed > 0) {
                $estimate = $this->fallbackCost($shopId, $variation);
                $this->allocate($shopId, $variation, null, $consumerType, $consumerId, $saleItemId, $needed, $estimate, true);
                $totalCost += $needed * (float) $estimate;
                $costKnown = $costKnown && $estimate !== null;
            }

            $this->changeStock($shopId, $variation, -$quantity);
            $totalCost = round($totalCost, 2);
            $unitCost = $quantity > 0 ? round($totalCost / $quantity, 2) : null;
            $movement = $this->recordMovement($shopId, $variation, $type, $quantity, $before, $before - $quantity, $costKnown ? $unitCost : null, $context);

            return new StockChange($movement, $quantity, $totalCost, $costKnown);
        });
    }

    /**
     * Gives back everything one consumer took (stage 10, step 3): a sale line,
     * adjustment line or supplier-return line, found through consumerKey(), so
     * any consumer releases through this one method. Settled units go back into
     * the batch they came from (a new `release` batch at their cost if it no
     * longer exists), so FIFO order and cost stay exact; provisional units come
     * back with no batch. Stock rises by the total, one movement of $type is
     * written at the weighted cost released, the allocations are deleted (their
     * history is in the movement and the audit log), and the batches given
     * units then settle waiting below-zero sales, oldest first. The consumer's
     * own cost figures are left as they were. Nothing to release (already
     * released, or never consumed) gives null and changes nothing.
     *
     * @param  array{reference?: Model|null, notes?: string|null, user_id?: int|null}  $context
     */
    public function release(Model $consumer, StockMovementType $type, array $context = []): ?StockChange
    {
        [$consumerType, $consumerId] = $this->consumerKey($consumer);

        return DB::transaction(function () use ($consumerType, $consumerId, $type, $context): ?StockChange {
            $first = DB::table('cost_allocations')->where('consumer_type', $consumerType)->where('consumer_id', $consumerId)
                ->orderBy('id')->first(['shop_id', 'product_id', 'variation_id']);
            if ($first === null) {
                return null;
            }

            $shopId = (int) $first->shop_id;
            $variation = (new ProductVariation)->forceFill(['id' => (int) $first->variation_id, 'product_id' => (int) $first->product_id]);
            $before = $this->lockStock($shopId, $variation);

            $allocations = DB::table('cost_allocations')->where('consumer_type', $consumerType)->where('consumer_id', $consumerId)
                ->where('shop_id', $shopId)->where('variation_id', $variation->id)
                ->orderBy('id')->lockForUpdate()->get();

            $quantity = 0;
            $totalCost = 0.0;
            $costKnown = true;
            $batches = [];

            foreach ($allocations as $allocation) {
                $units = (int) $allocation->quantity_allocated;
                $cost = $allocation->unit_cost === null ? null : (float) $allocation->unit_cost;
                $quantity += $units;
                $totalCost += $units * (float) $cost;
                $costKnown = $costKnown && $cost !== null;

                if ($allocation->is_provisional) {
                    continue; // stock comes back with no batch, as it left
                }

                $layerId = $allocation->cost_layer_id === null ? null : (int) $allocation->cost_layer_id;
                if ($layerId !== null && DB::table('cost_layers')->where('id', $layerId)->lockForUpdate()->exists()) {
                    DB::table('cost_layers')->where('id', $layerId)->update([
                        'remaining_quantity' => DB::raw('remaining_quantity + '.$units),
                        'consumed_at' => null,
                        'updated_at' => now(),
                    ]);
                } else {
                    $layerId = $this->createLayer($shopId, $variation, $units, $cost, ['source_type' => CostLayer::SOURCE_RELEASE, 'source_id' => $consumerId]);
                }
                $batches[$layerId] = true;
            }

            DB::table('cost_allocations')->whereIn('id', $allocations->pluck('id'))->delete();
            $this->changeStock($shopId, $variation, $quantity);

            $totalCost = round($totalCost, 2);
            $unitCost = $quantity > 0 && $costKnown ? round($totalCost / $quantity, 2) : null;
            $movement = $this->recordMovement($shopId, $variation, $type, $quantity, $before, $before + $quantity, $unitCost, $context);

            // Units given back to a batch settle sales made below zero, oldest batch first.
            $order = DB::table('cost_layers')->whereIn('id', array_keys($batches))->orderBy('received_at')->orderBy('id')->pluck('id');
            foreach ($order as $layerId) {
                $this->settleProvisional($shopId, $variation, (int) $layerId);
            }

            return new StockChange($movement, $quantity, $totalCost, $costKnown);
        });
    }

    /**
     * Moves a shop's stock of a variation to $target (a figure set on a
     * product or variation): the difference is received at the fallback cost
     * or written off, oldest layers first.
     */
    public function setLevel(int $shopId, ProductVariation $variation, int $target, array $context = []): ?StockChange
    {
        return DB::transaction(function () use ($shopId, $variation, $target, $context): ?StockChange {
            $delta = $target - $this->lockStock($shopId, $variation);

            return match (true) {
                $delta > 0 => $this->receive($shopId, $variation, $delta, $this->fallbackCost($shopId, $variation), StockMovementType::ADJUSTMENT_ADD, [...$context, 'source_type' => CostLayer::SOURCE_ADJUSTMENT]),
                $delta < 0 => $this->consume($shopId, $variation, -$delta, StockMovementType::ADJUSTMENT_REMOVE, $variation, $context),
                default => null,
            };
        });
    }

    /**
     * Takes a sale line's stock from its shop and sets its cost and profit
     * from the layers it consumed. Call recostSale() once all lines are done.
     * A product that doesn't track stock moves nothing (stage 10, step 1a): no
     * movement, no allocation; its line is costed at the variation's cost.
     */
    public function sellItem(SaleItem $item, ProductVariation $variation, array $context = []): ?StockChange
    {
        if (! $this->tracksStock($variation)) {
            $cost = $variation->cost_price ?? ($variation->is_default ? DB::table('products')->where('id', $variation->product_id)->value('cost_price') : null);
            $this->applyItemCost($item->id, round((int) $item->quantity * (float) $cost, 2));

            return null;
        }

        // The selling shop, recorded on the line (stage 10, step 1a), else its sale's:
        // converted online orders record no shop on their lines.
        $shopId = (int) ($item->shop_id ?? DB::table('sales')->where('id', $item->sale_id)->value('shop_id'));
        $change = $this->consume($shopId, $variation, (int) $item->quantity, StockMovementType::SALE, $item, ['reference' => $context['reference'] ?? $item->sale, ...$context]);
        $this->applyItemCost($item->id, $change->totalCost);

        return $change;
    }

    /**
     * The cost new stock takes when nothing better is known: the latest layer
     * with a cost in this shop, else the shop's cost override, else the
     * variation's cost (a default variation falls back to its product's).
     */
    public function fallbackCost(int $shopId, ProductVariation $variation): ?float
    {
        $cost = DB::table('cost_layers')->where('shop_id', $shopId)->where('variation_id', $variation->id)
            ->whereNotNull('unit_cost')->orderByDesc('received_at')->orderByDesc('id')->value('unit_cost')
            ?? DB::table('shop_variation')->where('shop_id', $shopId)->where('variation_id', $variation->id)->value('cost_price')
            ?? $variation->cost_price
            ?? ($variation->is_default ? DB::table('products')->where('id', $variation->product_id)->value('cost_price') : null);

        return $cost === null ? null : (float) $cost;
    }

    /**
     * What taking $quantity of a variation from a shop would cost now, per unit,
     * without taking it (stage 10, step 1g: an adjustment's value before it is
     * applied): the batches oldest first, then the fallback cost for a
     * shortfall, as consume() would. Null when any unit's cost is unknown.
     */
    public function costToConsume(int $shopId, ProductVariation $variation, int $quantity): ?float
    {
        $quantity = abs($quantity);
        $needed = $quantity;
        $total = 0.0;

        foreach (DB::table('cost_layers')->where('shop_id', $shopId)->where('variation_id', $variation->id)->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')->orderBy('id')->get(['remaining_quantity', 'unit_cost']) as $layer) {
            if ($needed <= 0) {
                break;
            }
            if ($layer->unit_cost === null) {
                return null;
            }
            $take = min($needed, (int) $layer->remaining_quantity);
            $total += $take * (float) $layer->unit_cost;
            $needed -= $take;
        }

        if ($needed > 0) {
            $fallback = $this->fallbackCost($shopId, $variation);
            if ($fallback === null) {
                return null;
            }
            $total += $needed * $fallback;
        }

        return $quantity > 0 ? round($total / $quantity, 2) : null;
    }

    /**
     * The cost of the latest batch of a variation at a shop that has one, or
     * null: what found stock is received at (stage 10, step 1g; stage 13 does the
     * same for stocktake surpluses), never a cost_price nobody entered for it.
     */
    public function latestBatchCost(int $shopId, ProductVariation $variation): ?float
    {
        $cost = DB::table('cost_layers')->where('shop_id', $shopId)->where('variation_id', $variation->id)
            ->whereNotNull('unit_cost')->orderByDesc('received_at')->orderByDesc('id')->value('unit_cost');

        return $cost === null ? null : (float) $cost;
    }

    /**
     * The shop's stock of a variation.
     */
    public function quantity(int $shopId, int $variationId): int
    {
        return (int) DB::table('shop_variation')->where('shop_id', $shopId)->where('variation_id', $variationId)->value('stock_quantity');
    }

    /**
     * Updates a sale's cost and profit from its lines (after selling them, or
     * after a provisional cost is re-costed).
     */
    /**
     * @return array{total_cost: float, total_profit: float} the figures written,
     *                                                       to set on a sale held in memory
     */
    public function recostSale(int $saleId): array
    {
        $sale = DB::table('sales')->where('id', $saleId)->first(['total_amount', 'tax_amount']);
        if ($sale === null) {
            return ['total_cost' => 0.0, 'total_profit' => 0.0];
        }

        $totalCost = round((float) DB::table('sale_items')->where('sale_id', $saleId)->sum('total_cost'), 2);
        $figures = [
            'total_cost' => $totalCost,
            // Profit is net of VAT (docs/vat.md).
            'total_profit' => round((float) $sale->total_amount - (float) $sale->tax_amount - $totalCost, 2),
        ];

        DB::table('sales')->where('id', $saleId)->update([...$figures, 'updated_at' => now()]);

        return $figures;
    }

    // --- internals -----------------------------------------------------------

    private function tracksStock(ProductVariation $variation): bool
    {
        return (bool) (DB::table('products')->where('id', $variation->product_id)->value('track_stock') ?? true);
    }

    /**
     * Locks the shop's stock row for the variation, creating it at zero when
     * the variation hasn't been held there before. Returns the quantity.
     */
    private function lockStock(int $shopId, ProductVariation $variation): int
    {
        DB::table('shop_variation')->insertOrIgnore([
            'shop_id' => $shopId,
            'product_id' => $variation->product_id,
            'variation_id' => $variation->id,
            'stock_quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('shop_variation')
            ->where('shop_id', $shopId)->where('variation_id', $variation->id)
            ->lockForUpdate()->value('stock_quantity');
    }

    private function changeStock(int $shopId, ProductVariation $variation, int $delta): void
    {
        DB::table('shop_variation')->where('shop_id', $shopId)->where('variation_id', $variation->id)
            ->update(['stock_quantity' => DB::raw('stock_quantity + ('.$delta.')'), 'updated_at' => now()]);

        $this->pushStock($shopId, $variation);
    }

    /**
     * Sends the shop's new figure to the shop's online stores, once the change
     * commits (stage 1c). Only this shop's stores: each shows its own shop's
     * stock. Not while a store's own figures are being written in, so a store
     * never gets its own change echoed back.
     */
    private function pushStock(int $shopId, ProductVariation $variation): void
    {
        if (ProductObserver::$syncInProgress || ! $this->shopHasStore($shopId)) {
            return;
        }

        PushShopStockJob::dispatch($shopId, $variation->product_id)->afterCommit();
    }

    /** @var array<int, bool> whether each shop has an online store connected */
    private array $shopHasStore = [];

    private function shopHasStore(int $shopId): bool
    {
        return $this->shopHasStore[$shopId] ??= (static function (?Shop $shop): bool {
            return $shop !== null && ($shop->isIntegrationEnabled('woocommerce') || $shop->isIntegrationEnabled('shopify'));
        })(Shop::find($shopId));
    }

    private function createLayer(int $shopId, ProductVariation $variation, int $quantity, ?float $unitCost, array $context): int
    {
        return DB::table('cost_layers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'business_id' => $this->businessOf($shopId),
            'shop_id' => $shopId,
            'product_id' => $variation->product_id,
            'variation_id' => $variation->id,
            'source_type' => $context['source_type'] ?? CostLayer::SOURCE_INTAKE,
            'source_id' => $context['source_id'] ?? null,
            'stock_intake_id' => $context['stock_intake_id'] ?? null,
            'unit_cost' => $unitCost,
            'original_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'received_at' => $context['received_at'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function allocate(int $shopId, ProductVariation $variation, ?int $layerId, string $consumerType, int $consumerId, ?int $saleItemId, int $quantity, ?float $unitCost, bool $provisional): void
    {
        DB::table('cost_allocations')->insert([
            'uuid' => (string) Str::uuid(),
            'business_id' => $this->businessOf($shopId),
            'shop_id' => $shopId,
            'product_id' => $variation->product_id,
            'variation_id' => $variation->id,
            'cost_layer_id' => $layerId,
            'consumer_type' => $consumerType,
            'consumer_id' => $consumerId,
            'sale_item_id' => $saleItemId,
            'quantity_allocated' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $unitCost === null ? null : round($quantity * $unitCost, 2),
            'is_provisional' => $provisional,
            'provisional_unit_cost' => $provisional ? $unitCost : null,
            'allocated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * A new layer first covers units sold below zero, oldest first: those
     * allocations take the layer's cost, and their sales are re-costed.
     * Voided sales are skipped.
     */
    private function settleProvisional(int $shopId, ProductVariation $variation, int $layerId): void
    {
        $layer = DB::table('cost_layers')->where('id', $layerId)->first();
        $available = (int) $layer->remaining_quantity;
        $cost = $layer->unit_cost === null ? null : (float) $layer->unit_cost;

        $pending = DB::table('cost_allocations')
            ->where('shop_id', $shopId)->where('variation_id', $variation->id)->where('is_provisional', true)
            // A voided sale's units never take arriving stock (stage 10, step 4): a sale
            // voided before this stage still has its allocations.
            ->where(fn ($query) => $query->whereNull('sale_item_id')->orWhereNotIn('sale_item_id', DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.status', 'voided')->select('sale_items.id')))
            ->orderBy('allocated_at')->orderBy('id')
            ->lockForUpdate()
            ->get();

        $recost = new Collection;

        foreach ($pending as $allocation) {
            if ($available <= 0) {
                break;
            }
            $take = min($available, (int) $allocation->quantity_allocated);
            $settled = [
                'cost_layer_id' => $layerId,
                'unit_cost' => $cost,
                'total_cost' => $cost === null ? null : round($take * $cost, 2),
                'is_provisional' => false,
                'settled_at' => now(),
                'updated_at' => now(),
            ];

            if ($take === (int) $allocation->quantity_allocated) {
                DB::table('cost_allocations')->where('id', $allocation->id)->update($settled);
            } else {
                // Part of it: the rest stays provisional.
                $left = (int) $allocation->quantity_allocated - $take;
                DB::table('cost_allocations')->where('id', $allocation->id)->update([
                    'quantity_allocated' => $left,
                    'total_cost' => $allocation->unit_cost === null ? null : round($left * (float) $allocation->unit_cost, 2),
                    'updated_at' => now(),
                ]);
                $copy = (array) $allocation;
                unset($copy['id']);
                DB::table('cost_allocations')->insert([...$copy, ...$settled, 'uuid' => (string) Str::uuid(), 'quantity_allocated' => $take, 'created_at' => now()]);
            }

            $available -= $take;
            if ($allocation->sale_item_id !== null) {
                $recost->push((int) $allocation->sale_item_id);
            }
        }

        DB::table('cost_layers')->where('id', $layerId)->update([
            'remaining_quantity' => $available,
            'consumed_at' => $available === 0 ? now() : null,
        ]);

        foreach ($recost->unique() as $saleItemId) {
            $total = round((float) DB::table('cost_allocations')->where('sale_item_id', $saleItemId)->sum('total_cost'), 2);
            $this->applyItemCost($saleItemId, $total);
            $this->recostSale((int) DB::table('sale_items')->where('id', $saleItemId)->value('sale_id'));
        }
    }

    /**
     * Sets a sale line's cost and profit (profit is net of VAT: the line's
     * taxable amount less its cost, as CalculateSaleTotals works it out).
     */
    private function applyItemCost(int $saleItemId, float $totalCost): void
    {
        $item = DB::table('sale_items')->where('id', $saleItemId)->first(['quantity', 'taxable_amount']);
        $taxable = (float) $item->taxable_amount;
        $profit = round($taxable - $totalCost, 2);

        DB::table('sale_items')->where('id', $saleItemId)->update([
            'unit_cost' => (int) $item->quantity > 0 ? round($totalCost / (int) $item->quantity, 2) : 0,
            'total_cost' => $totalCost,
            'profit' => $profit,
            'profit_margin' => $taxable > 0 ? round($profit / $taxable * 100, 2) : 0,
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array{reference?: Model|null, notes?: string|null, user_id?: int|null}  $context
     */
    private function recordMovement(int $shopId, ProductVariation $variation, StockMovementType $type, int $quantity, int $before, int $after, ?float $unitCost, array $context): StockMovement
    {
        $reference = $context['reference'] ?? null;
        $notes = $context['notes'] ?? null;
        if ($after < 0) {
            $notes = trim(($notes ?? '').' Stock below zero: costed provisionally until stock arrives.');
        }

        return StockMovement::create([
            'shop_id' => $shopId,
            'product_id' => $variation->product_id,
            'variation_id' => $variation->id,
            'movement_type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'unit_cost' => $unitCost,
            'reference_type' => $reference ? $reference::class : null,
            'reference_uuid' => $reference?->uuid,
            'notes' => $notes,
            'created_by' => $context['user_id'] ?? auth()->id(),
            'updated_by' => $context['user_id'] ?? auth()->id(),
        ]);
    }

    /**
     * @return array{string, int, int|null}
     */
    private function consumerKey(Model $consumer): array
    {
        return match (true) {
            $consumer instanceof SaleItem => [CostAllocation::CONSUMER_SALE_ITEM, (int) $consumer->id, (int) $consumer->id],
            $consumer instanceof StockAdjustmentItem => [CostAllocation::CONSUMER_ADJUSTMENT_ITEM, (int) $consumer->id, null],
            $consumer instanceof PurchaseReturnItem => [CostAllocation::CONSUMER_PURCHASE_RETURN_ITEM, (int) $consumer->id, null],
            default => [Str::snake(class_basename($consumer)), (int) $consumer->getKey(), null],
        };
    }

    /** @var array<int, int> */
    private array $businessByShop = [];

    private function businessOf(int $shopId): int
    {
        return $this->businessByShop[$shopId] ??= (int) DB::table('shops')->where('id', $shopId)->value('business_id');
    }
}
