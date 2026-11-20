<?php

namespace App\Services\Stock;

use App\Casts\Quantity;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
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
use App\Support\Stock\Expiry;
use App\Support\Stock\SellableStock;
use App\Support\Sync\SyncIssues;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
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
 * Quantities are decimal (stage 11, step 1): held as scale-3 strings and
 * moved with `bcmath` throughout — never a float, whose binary rounding would
 * drift across repeated additions. Costs per base unit stay plain floats
 * (they're multiplied once or twice per call, not accumulated), rounded to
 * cents at the point they become a money figure.
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
    public function receive(int $shopId, ProductVariation $variation, int|float|string $quantity, ?float $unitCost, StockMovementType $type, array $context = []): StockChange
    {
        $quantity = $this->absQuantity($quantity);

        return DB::transaction(function () use ($shopId, $variation, $quantity, $unitCost, $type, $context): StockChange {
            $before = $this->lockStock($shopId, $variation);
            $this->changeStock($shopId, $variation, $quantity);

            $layerId = null;
            if (bccomp($quantity, '0', 3) > 0) {
                $layerId = $this->createLayer($shopId, $variation, $quantity, $unitCost, $context);
                $this->settleProvisional($shopId, $variation, $layerId);
            }

            $after = bcadd($before, $quantity, 3);
            $movement = $this->recordMovement($shopId, $variation, $type, $quantity, $before, $after, $unitCost, $context);

            // Stock back at zero or above resolves any open negative_stock review for this shop and
            // variation (stage 11 step 4 follow-up: the overselling policy's negative_stock review).
            if (bccomp($after, '0', 3) >= 0) {
                app(SyncIssues::class)->resolveNegativeStock($shopId, $variation->id);
            }

            return new StockChange($movement, $quantity, round($this->multiply($quantity, $unitCost), 2), $unitCost !== null);
        });
    }

    /**
     * Stock out, costed from the layers oldest first. $preferredLayerId (a
     * purchase return's own intake) is taken first.
     *
     * @param  array{reference?: Model|null, notes?: string|null, user_id?: int|null}  $context
     */
    public function consume(int $shopId, ProductVariation $variation, int|float|string $quantity, StockMovementType $type, Model $consumer, array $context = [], ?int $preferredLayerId = null): StockChange
    {
        $quantity = $this->absQuantity($quantity);

        return DB::transaction(function () use ($shopId, $variation, $quantity, $type, $consumer, $context, $preferredLayerId): StockChange {
            $before = $this->lockStock($shopId, $variation);

            // Overselling (decided 5 November 2026, stage 11 step 4 follow-up): only a caller that
            // opts in (sellItem(), for the web POS and a direct flat API sale) enforces this; a
            // till's push, an online order or abandoned-cart conversion, and every other consumer
            // (adjustments, write-offs, purchase returns) never pass enforce_stock, so they are
            // unaffected. Checked right after the row lock above, so two simultaneous sales for the
            // shop's last unit can't both pass this check before either commits.
            // The check counts only what a sale can be given: quarantined and expired stock is excluded, so an
            // expired-only shelf cannot pass it. A medical product is held to this whatever the caller (every sale
            // goes through sellItem(), which passes exclude_expired), since its expired stock is never sold.
            $sale = (bool) ($context['exclude_expired'] ?? false);
            $medical = (bool) DB::table('products')->where('id', $variation->product_id)->value('is_medical');
            $enforce = (bool) ($context['enforce_stock'] ?? false);
            $sellableNow = $enforce || ($sale && $medical) ? SellableStock::sellableOf(Shop::query()->findOrFail($shopId), $variation->id) : $before;

            if ($enforce && empty($context['oversell_reason']) && bccomp($sellableNow, $quantity, 3) < 0) {
                throw BusinessRuleException::because(
                    __('Only :available left of this item at this shop; :needed needed.', ['available' => $sellableNow, 'needed' => $quantity]),
                    'insufficient_stock',
                );
            }

            if ($sale && $medical && bccomp($sellableNow, $quantity, 3) < 0) {
                throw BusinessRuleException::because(
                    __('Medical stock past its expiry cannot be sold: only :available sellable; :needed needed.', ['available' => $sellableNow, 'needed' => $quantity]),
                    'medical_unsellable',
                );
            }

            [$consumerType, $consumerId, $saleItemId] = $this->consumerKey($consumer);

            // Expired batches are never sold (stage 11, step 5): only sellItem() passes
            // exclude_expired; every other consumer (adjustments, write-offs, purchase returns) can
            // still take them. FEFO/FIFO is the shop's business's own setting.
            $excludeExpired = (bool) ($context['exclude_expired'] ?? false);
            $shop = Shop::query()->find($shopId);
            $boundary = $shop !== null ? Expiry::firstSellableDate($shop)->toDateString() : null;
            $fefo = $shop !== null && $shop->business?->stockConsumption() === 'fefo';

            // A decrease that names its batch (stage 11, review follow-up) takes only from that batch: it
            // never spills into other batches, and a shortfall is refused (the transaction rolls back).
            $exactLayer = (bool) ($context['exact_layer'] ?? false) && $preferredLayerId !== null;

            $layers = DB::table('cost_layers')
                ->where('shop_id', $shopId)->where('variation_id', $variation->id)->where('remaining_quantity', '>', 0)
                ->when($exactLayer, fn ($query) => $query->where('id', $preferredLayerId))
                ->when(! ($context['include_quarantine'] ?? false), fn ($query) => $query->whereNull('quarantine_status'))
                ->when($excludeExpired && $boundary !== null, fn ($query) => $query->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', $boundary)->orWhere(fn ($waived) => $waived->whereNotNull('stock_release_id')->whereIn('product_id', $this->nonMedicalProducts()))))
                ->when($preferredLayerId, fn ($query) => $query->orderByRaw('case when id = ? then 0 else 1 end', [$preferredLayerId]))
                ->when($fefo, fn ($query) => $query->orderByRaw('expiry_date is null')->orderBy('expiry_date'))
                ->orderBy('received_at')->orderBy('id')
                ->lockForUpdate()
                ->get();

            $needed = $quantity;
            $totalCost = 0.0;
            $costKnown = true;

            foreach ($layers as $layer) {
                if (bccomp($needed, '0', 3) <= 0) {
                    break;
                }
                $remaining = Quantity::normalize($layer->remaining_quantity);
                $take = bccomp($needed, $remaining, 3) <= 0 ? $needed : $remaining;
                $cost = $layer->unit_cost === null ? null : (float) $layer->unit_cost;

                DB::table('cost_layers')->where('id', $layer->id)->update([
                    'remaining_quantity' => DB::raw('remaining_quantity - ('.$take.')'),
                    'consumed_at' => bccomp($take, $remaining, 3) === 0 ? now() : null,
                    'updated_at' => now(),
                ]);
                $this->allocate($shopId, $variation, $layer->id, $consumerType, $consumerId, $saleItemId, $take, $cost, false);

                $totalCost += $this->multiply($take, $cost);
                $costKnown = $costKnown && $cost !== null;
                $needed = bcsub($needed, $take, 3);
            }

            if ($exactLayer && bccomp($needed, '0', 3) > 0) {
                throw BusinessRuleException::because(
                    __('That batch has only :available left; :needed needed.', ['available' => bcsub($quantity, $needed, 3), 'needed' => $quantity]),
                    'batch_insufficient',
                );
            }

            // Sold below zero: allocated now at the last known cost, re-costed
            // when stock arrives.
            if (bccomp($needed, '0', 3) > 0) {
                $estimate = $this->fallbackCost($shopId, $variation);
                $this->allocate($shopId, $variation, null, $consumerType, $consumerId, $saleItemId, $needed, $estimate, true);
                $totalCost += $this->multiply($needed, $estimate);
                $costKnown = $costKnown && $estimate !== null;
            }

            $this->changeStock($shopId, $variation, bcmul($quantity, '-1', 3));
            $totalCost = round($totalCost, 2);
            $unitCost = bccomp($quantity, '0', 3) > 0 ? round($totalCost / (float) $quantity, 2) : null;
            $after = bcsub($before, $quantity, 3);
            $movement = $this->recordMovement($shopId, $variation, $type, $quantity, $before, $after, $costKnown ? $unitCost : null, $context);

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

            $quantity = '0.000';
            $totalCost = 0.0;
            $costKnown = true;
            $batches = [];

            foreach ($allocations as $allocation) {
                $units = Quantity::normalize($allocation->quantity_allocated);
                $cost = $allocation->unit_cost === null ? null : (float) $allocation->unit_cost;
                $quantity = bcadd($quantity, $units, 3);
                $totalCost += $this->multiply($units, $cost);
                $costKnown = $costKnown && $cost !== null;

                if ($allocation->is_provisional) {
                    continue; // stock comes back with no batch, as it left
                }

                $layerId = $allocation->cost_layer_id === null ? null : (int) $allocation->cost_layer_id;
                if ($layerId !== null && DB::table('cost_layers')->where('id', $layerId)->lockForUpdate()->exists()) {
                    DB::table('cost_layers')->where('id', $layerId)->update([
                        'remaining_quantity' => DB::raw('remaining_quantity + ('.$units.')'),
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
            $unitCost = bccomp($quantity, '0', 3) > 0 && $costKnown ? round($totalCost / (float) $quantity, 2) : null;
            $after = bcadd($before, $quantity, 3);
            $movement = $this->recordMovement($shopId, $variation, $type, $quantity, $before, $after, $unitCost, $context);

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
     * product or variation): the difference is received at $unitCost (else
     * the fallback cost) or written off, oldest layers first. $context may
     * give its own 'source_type' (stage 11, step 4: opening stock uses
     * CostLayer::SOURCE_OPENING); it defaults to an adjustment.
     */
    public function setLevel(int $shopId, ProductVariation $variation, int|float|string $target, array $context = [], ?float $unitCost = null): ?StockChange
    {
        return DB::transaction(function () use ($shopId, $variation, $target, $context, $unitCost): ?StockChange {
            $target = Quantity::normalize($target);
            $delta = bcsub($target, $this->lockStock($shopId, $variation), 3);
            $cmp = bccomp($delta, '0', 3);

            return match (true) {
                $cmp > 0 => $this->receive($shopId, $variation, $delta, $unitCost ?? $this->fallbackCost($shopId, $variation), StockMovementType::ADJUSTMENT_ADD, [...$context, 'source_type' => $context['source_type'] ?? CostLayer::SOURCE_ADJUSTMENT]),
                $cmp < 0 => $this->consume($shopId, $variation, bcmul($delta, '-1', 3), StockMovementType::ADJUSTMENT_REMOVE, $variation, $context),
                default => null,
            };
        });
    }

    /**
     * Takes a sale line's stock from its shop and sets its cost and profit
     * from the layers it consumed. Call recostSale() once all lines are done.
     * A product that doesn't track stock moves nothing (stage 10, step 1a): no
     * movement, no allocation; its line is costed at the variation's cost.
     * Moves base_quantity (stage 11, step 3: the line's own quantity is in
     * whatever unit it was sold in); applyItemCost() still divides by
     * quantity, so unit_cost lands per that unit, as unit_price already is.
     */
    public function sellItem(SaleItem $item, ProductVariation $variation, array $context = []): ?StockChange
    {
        if (! $this->tracksStock($variation)) {
            $cost = $variation->cost_price ?? ($variation->is_default ? DB::table('products')->where('id', $variation->product_id)->value('cost_price') : null);
            $totalCost = round($this->multiply(Quantity::normalize($item->base_quantity), $cost === null ? null : (float) $cost), 2);
            $this->applyItemCost($item->id, $totalCost);
            DB::table('sale_items')->where('id', $item->id)->update(['cost_known' => $cost !== null]);

            return null;
        }

        // The selling shop, recorded on the line (stage 10, step 1a), else its sale's:
        // converted online orders record no shop on their lines.
        $shopId = (int) ($item->shop_id ?? DB::table('sales')->where('id', $item->sale_id)->value('shop_id'));
        // Expired batches are never sold (stage 11, step 5): the only caller that excludes them.
        $change = $this->consume($shopId, $variation, Quantity::normalize($item->base_quantity), StockMovementType::SALE, $item, [
            ...$context, 'reference' => $context['reference'] ?? $item->sale, 'exclude_expired' => true,
        ]);
        $this->applyItemCost($item->id, $change->totalCost);
        // Whether every unit's cost is known (stage 10, step 6).
        DB::table('sale_items')->where('id', $item->id)->update(['cost_known' => $change->costKnown]);

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
    public function costToConsume(int $shopId, ProductVariation $variation, int|float|string $quantity, ?int $preferredLayerId = null, bool $exactLayer = false, bool $includeQuarantine = false): ?float
    {
        $quantity = $this->absQuantity($quantity);
        $needed = $quantity;
        $total = 0.0;

        // The same layers, in the same order, as consume() will take them, so an approval is valued at what it
        // will actually move: the named batch alone when one is chosen, else the preferred one first, then FEFO or FIFO.
        $shop = Shop::query()->find($shopId);
        $fefo = $shop !== null && $shop->business?->stockConsumption() === 'fefo';
        $exact = $exactLayer && $preferredLayerId !== null;

        $layers = DB::table('cost_layers')->where('shop_id', $shopId)->where('variation_id', $variation->id)->where('remaining_quantity', '>', 0)
            ->when(! $includeQuarantine, fn ($query) => $query->whereNull('quarantine_status'))
            ->when($exact, fn ($query) => $query->where('id', $preferredLayerId))
            ->when($preferredLayerId, fn ($query) => $query->orderByRaw('case when id = ? then 0 else 1 end', [$preferredLayerId]))
            ->when($fefo, fn ($query) => $query->orderByRaw('expiry_date is null')->orderBy('expiry_date'))
            ->orderBy('received_at')->orderBy('id')
            ->get(['remaining_quantity', 'unit_cost']);

        foreach ($layers as $layer) {
            if (bccomp($needed, '0', 3) <= 0) {
                break;
            }
            if ($layer->unit_cost === null) {
                return null;
            }
            $remaining = Quantity::normalize($layer->remaining_quantity);
            $take = bccomp($needed, $remaining, 3) <= 0 ? $needed : $remaining;
            $total += $this->multiply($take, (float) $layer->unit_cost);
            $needed = bcsub($needed, $take, 3);
        }

        if (bccomp($needed, '0', 3) > 0) {
            // A named batch cannot be spilled into, so its shortfall has no cost (consume() refuses it).
            if ($exact) {
                return null;
            }
            $fallback = $this->fallbackCost($shopId, $variation);
            if ($fallback === null) {
                return null;
            }
            $total += $this->multiply($needed, $fallback);
        }

        return bccomp($quantity, '0', 3) > 0 ? round($total / (float) $quantity, 2) : null;
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
     * Moves the shop's stock row of a variation on (its `updated_at`) without
     * changing stock, so tills pull it again (stage 10, step 5c: a pending
     * adjustment decrease changes the units it shows as pending removal).
     */
    public function touch(int $shopId, int $variationId): void
    {
        DB::table('shop_variation')->where('shop_id', $shopId)->where('variation_id', $variationId)->update(['updated_at' => now()]);
    }

    /**
     * Locks a variation's stock row for the rest of the caller's transaction: the same first lock a sale takes, so a
     * release and a sale of the same variation serialise. Call it before locking any layer.
     */
    public function lockVariationStock(int $shopId, ProductVariation $variation): void
    {
        $this->lockStock($shopId, $variation);
    }

    /**
     * Moves a quantity out of a quarantined layer into a new sellable layer that keeps the batch, expiry and cost (stage 11,
     * quarantine policy). On-hand stock does not change: the stock was on hand already, and only its status changes.
     * The caller must hold the variation's stock lock and the source layer's lock, and must call settleReleased() after
     * linking the new layer to its release record.
     */
    public function addQuarantineRelease(CostLayer $source, string $quantity): CostLayer
    {
        $remaining = Quantity::normalize($source->remaining_quantity);
        $source->update([
            'remaining_quantity' => bcsub($remaining, $quantity, 3),
            'consumed_at' => bccomp($remaining, $quantity, 3) === 0 ? now() : null,
        ]);

        return CostLayer::query()->create([
            'uuid' => (string) Str::uuid(),
            'business_id' => $source->business_id,
            'shop_id' => $source->shop_id,
            'product_id' => $source->product_id,
            'variation_id' => $source->variation_id,
            'source_type' => CostLayer::SOURCE_QUARANTINE_RELEASE,
            'source_id' => $source->id,
            'unit_cost' => $source->unit_cost,
            'batch_number' => $source->batch_number,
            'expiry_date' => $source->expiry_date,
            'original_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'received_at' => $source->received_at,
        ]);
    }

    /**
     * Settles the sales still waiting for stock with a released layer, and marks the variation's stock changed so an
     * incremental sync sends it (stage 11, quarantine policy). Call it after the layer is linked to its release.
     */
    public function settleReleased(CostLayer $released): void
    {
        $variation = ProductVariation::withoutGlobalScope(ProductVariation::HIDE_DEFAULT)->findOrFail($released->variation_id);

        $this->settleProvisional($released->shop_id, $variation, $released->id);
        $this->touch($released->shop_id, $released->variation_id);
    }

    /**
     * Products that are not medical: the only ones whose released stock is exempt from expiry.
     */
    private function nonMedicalProducts(): Builder
    {
        return DB::table('products')->where('is_medical', false)->select('id');
    }

    /**
     * The shop's stock of a variation, in whole base units (rounded down, as
     * every whole-number integration point reads it). exactQuantity() gives
     * the full decimal figure.
     */
    public function quantity(int $shopId, int $variationId): int
    {
        return (int) $this->exactQuantity($shopId, $variationId);
    }

    /**
     * The shop's sellable stock of a variation, whole units as the online stores take them: the total less the
     * batches already expired (their expiry is before the shop's first sellable day, the same line sellItem()
     * uses), floored at zero. The stores are sent this, so they stop offering what can no longer be sold.
     */
    public function sellable(int $shopId, int $variationId): int
    {
        return (int) $this->sellableQuantity($shopId, $variationId);
    }

    /**
     * The shop's sellable stock of a variation at scale 3: what sales may take, excluding quarantined stock and
     * expired batches that no approved release covers. Floored at zero.
     */
    public function sellableQuantity(int $shopId, int $variationId): string
    {
        $shop = Shop::query()->find($shopId);

        if ($shop === null) {
            return $this->exactQuantity($shopId, $variationId);
        }

        $sellable = SellableStock::sellableOf($shop, $variationId);

        return bccomp($sellable, '0', 3) > 0 ? $sellable : '0.000';
    }

    /**
     * The shop's quarantined stock of a variation, at scale 3: on hand, but held out of sale until approved.
     */
    public function quarantinedQuantity(int $shopId, int $variationId): string
    {
        $shop = Shop::query()->find($shopId);

        return $shop === null ? '0.000' : (SellableStock::forShop($shop, $variationId)->get($variationId)['quarantined'] ?? '0.000');
    }

    /**
     * The shop's stock of a variation, as a scale-3 decimal string.
     */
    public function exactQuantity(int $shopId, int $variationId): string
    {
        $raw = DB::table('shop_variation')->where('shop_id', $shopId)->where('variation_id', $variationId)->value('stock_quantity');

        return Quantity::normalize($raw ?? 0);
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
     * the variation hasn't been held there before. Returns the quantity as a
     * scale-3 string.
     */
    private function lockStock(int $shopId, ProductVariation $variation): string
    {
        DB::table('shop_variation')->insertOrIgnore([
            'shop_id' => $shopId,
            'product_id' => $variation->product_id,
            'variation_id' => $variation->id,
            'stock_quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $raw = DB::table('shop_variation')
            ->where('shop_id', $shopId)->where('variation_id', $variation->id)
            ->lockForUpdate()->value('stock_quantity');

        return Quantity::normalize($raw ?? 0);
    }

    private function changeStock(int $shopId, ProductVariation $variation, int|float|string $delta): void
    {
        $delta = Quantity::normalize($delta);

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

    /**
     * $quantity as a non-negative scale-3 string, whatever numeric type it
     * arrived as.
     */
    private function absQuantity(int|float|string $quantity): string
    {
        $normalized = Quantity::normalize($quantity);

        return bccomp($normalized, '0', 3) < 0 ? bcmul($normalized, '-1', 3) : $normalized;
    }

    /**
     * A quantity (a scale-3 string) times a unit cost, as a float — the only
     * place `bcmath` quantities meet plain-float money, kept to one
     * multiplication so no error accumulates. Null cost counts as zero,
     * matching the callers' "unknown costs counted as zero" convention.
     */
    private function multiply(string $quantity, ?float $unitCost): float
    {
        return $unitCost === null ? 0.0 : (float) bcmul($quantity, (string) $unitCost, 6);
    }

    private function createLayer(int $shopId, ProductVariation $variation, int|float|string $quantity, ?float $unitCost, array $context): int
    {
        $quantity = Quantity::normalize($quantity);

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
            // A batch's number and expiry (stage 11, step 5): the caller already enforced that a
            // variation tracking batches gives both; this just carries whatever it was given.
            'batch_number' => $context['batch_number'] ?? null,
            'expiry_date' => isset($context['expiry_date']) ? Carbon::parse($context['expiry_date'])->toDateString() : null,
            'quarantine_status' => $context['quarantine_status'] ?? null,
            'quarantine_reason' => $context['quarantine_reason'] ?? null,
            'original_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'received_at' => $context['received_at'] ?? now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function allocate(int $shopId, ProductVariation $variation, ?int $layerId, string $consumerType, int $consumerId, ?int $saleItemId, int|float|string $quantity, ?float $unitCost, bool $provisional): void
    {
        $quantity = Quantity::normalize($quantity);

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
            'total_cost' => $unitCost === null ? null : round($this->multiply($quantity, $unitCost), 2),
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

        // An expired layer (a return of expired goods, say) is never sold, so it settles no sale: a sale still
        // waiting for stock stays provisional until stock that can be sold arrives.
        $shop = Shop::query()->find($shopId);
        if ($layer->quarantine_status !== null) {
            return;
        }
        // A released layer is exempt from expiry only for a non-medical product (checked when it is read, not stored).
        $waived = $layer->stock_release_id !== null && ! (bool) DB::table('products')->where('id', $layer->product_id)->value('is_medical');
        if ($layer->expiry_date !== null && $shop !== null && ! $waived && Carbon::parse($layer->expiry_date)->toDateString() < Expiry::firstSellableDate($shop)->toDateString()) {
            return;
        }

        $available = Quantity::normalize($layer->remaining_quantity);
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
            if (bccomp($available, '0', 3) <= 0) {
                break;
            }
            $allocated = Quantity::normalize($allocation->quantity_allocated);
            $take = bccomp($available, $allocated, 3) <= 0 ? $available : $allocated;
            // A batch with no cost keeps the provisional estimate (stage 10, step 6):
            // the line is marked cost unknown instead of dropping to no cost.
            $settledCost = $cost ?? ($allocation->unit_cost === null ? null : (float) $allocation->unit_cost);
            $settled = [
                'cost_layer_id' => $layerId,
                'unit_cost' => $settledCost,
                'total_cost' => $settledCost === null ? null : round($this->multiply($take, $settledCost), 2),
                'is_provisional' => false,
                'settled_at' => now(),
                'updated_at' => now(),
            ];

            if (bccomp($take, $allocated, 3) === 0) {
                DB::table('cost_allocations')->where('id', $allocation->id)->update($settled);
            } else {
                // Part of it: the rest stays provisional.
                $left = bcsub($allocated, $take, 3);
                DB::table('cost_allocations')->where('id', $allocation->id)->update([
                    'quantity_allocated' => $left,
                    'total_cost' => $allocation->unit_cost === null ? null : round($this->multiply($left, (float) $allocation->unit_cost), 2),
                    'updated_at' => now(),
                ]);
                $copy = (array) $allocation;
                unset($copy['id']);
                DB::table('cost_allocations')->insert([...$copy, ...$settled, 'uuid' => (string) Str::uuid(), 'quantity_allocated' => $take, 'created_at' => now()]);
            }

            $available = bcsub($available, $take, 3);
            if ($allocation->sale_item_id !== null) {
                $recost->push((int) $allocation->sale_item_id);
            }
        }

        DB::table('cost_layers')->where('id', $layerId)->update([
            'remaining_quantity' => $available,
            'consumed_at' => bccomp($available, '0', 3) === 0 ? now() : null,
        ]);

        foreach ($recost->unique() as $saleItemId) {
            $total = round((float) DB::table('cost_allocations')->where('sale_item_id', $saleItemId)->sum('total_cost'), 2);
            $this->applyItemCost($saleItemId, $total);
            DB::table('sale_items')->where('id', $saleItemId)->update(['cost_known' => $this->lineCostKnown($saleItemId)]);
            $this->recostSale((int) DB::table('sale_items')->where('id', $saleItemId)->value('sale_id'));
        }
    }

    /**
     * Whether every unit of a sale line has a known cost (stage 10, step 6):
     * no allocation with no cost, or from a batch with no cost.
     */
    public function lineCostKnown(int $saleItemId): bool
    {
        return ! DB::table('cost_allocations')
            ->leftJoin('cost_layers', 'cost_layers.id', '=', 'cost_allocations.cost_layer_id')
            ->where('cost_allocations.sale_item_id', $saleItemId)
            ->where(fn ($query) => $query->whereNull('cost_allocations.unit_cost')
                ->orWhere(fn ($layer) => $layer->whereNotNull('cost_allocations.cost_layer_id')->whereNull('cost_layers.unit_cost')))
            ->exists();
    }

    /**
     * Sets a sale line's cost and profit (profit is net of VAT: the line's
     * taxable amount less its cost, as CalculateSaleTotals works it out).
     */
    private function applyItemCost(int $saleItemId, float $totalCost): void
    {
        $item = DB::table('sale_items')->where('id', $saleItemId)->first(['quantity', 'taxable_amount']);
        $quantity = Quantity::normalize($item->quantity);
        $taxable = (float) $item->taxable_amount;
        $profit = round($taxable - $totalCost, 2);

        DB::table('sale_items')->where('id', $saleItemId)->update([
            'unit_cost' => bccomp($quantity, '0', 3) > 0 ? round($totalCost / (float) $quantity, 2) : 0,
            'total_cost' => $totalCost,
            'profit' => $profit,
            'profit_margin' => $taxable > 0 ? round($profit / $taxable * 100, 2) : 0,
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array{reference?: Model|null, notes?: string|null, user_id?: int|null}  $context
     */
    private function recordMovement(int $shopId, ProductVariation $variation, StockMovementType $type, string $quantity, string $before, string $after, ?float $unitCost, array $context): StockMovement
    {
        $reference = $context['reference'] ?? null;
        $notes = $context['notes'] ?? null;
        if (bccomp($after, '0', 3) < 0) {
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
