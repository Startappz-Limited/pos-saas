<?php

namespace App\Actions\Catalogue;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\ReturnItem;
use App\Models\SaleItem;
use App\Models\ShopUnitPrice;
use App\Models\ShopVariation;
use App\Models\UnassignedBarcode;
use Illuminate\Support\Facades\DB;

/**
 * Copies a business's catalogue facts into their new homes (stage 11, step 7, release 1): each
 * variation's base unit with its prices and barcode, shop price overrides for that unit, the costs
 * and reorder levels that belong to a shop's variation, and the lines that sold or returned it.
 *
 * Nothing is dropped here: the old columns stay until release 2, and readers see the same values.
 *
 * Two passes. The plan pass reads everything, lists every difference, clash and unlisted precision,
 * and writes nothing. The write pass runs only when the plan has no barcode clash, in one
 * transaction, so a clash never leaves half a copy. A second write pass finds nothing to change.
 */
class CopyCatalogueToUnits
{
    /** Names of whole-piece units (precision 0). */
    private const WHOLE = ['piece', 'pcs', 'each', 'unit'];

    /** Names of units sold by weight or volume (precision 3). */
    private const FRACTIONAL = ['kg', 'g', 'liter', 'litre', 'l', 'ml', 'meter', 'metre', 'm'];

    /**
     * @return array{units: int, precision_unlisted: list<string>, clashes: list<string>, unassigned: list<string>, differences: list<string>, shop_prices: int, costs: int, reorder_levels: int, lines: int, unresolved_returns: int, applied: bool}
     */
    public function run(bool $apply): array
    {
        $plan = $this->pass(write: false);

        if (! $apply || $plan['clashes'] !== []) {
            return $plan;
        }

        $report = DB::transaction(fn (): array => $this->pass(write: true));
        $report['applied'] = true;

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function pass(bool $write): array
    {
        $report = [
            'units' => 0, 'precision_unlisted' => [], 'clashes' => [], 'unassigned' => [], 'differences' => [],
            'shop_prices' => 0, 'costs' => 0, 'reorder_levels' => 0, 'lines' => 0, 'unresolved_returns' => 0, 'applied' => false,
        ];

        // Barcodes already on a unit, and those planned in this pass.
        $taken = ProductUnit::query()->whereNotNull('barcode')->pluck('barcode')->flip()->all();

        $variations = ProductVariation::query()->withoutGlobalScope(ProductVariation::HIDE_DEFAULT)
            ->with(['product' => fn ($query) => $query->withTrashed()->with('shops')])->orderBy('id')->get();

        foreach ($variations as $variation) {
            $product = $variation->product;

            if ($product === null) {
                continue;
            }

            $existing = ProductUnit::query()->withTrashed()->where('variation_id', $variation->id)->where('is_base', true)->first();

            if ($existing !== null) {
                // Already copied: a second pass changes nothing for the unit, only for lines and shop facts still unset.
                $this->copyShopFacts($variation, $product, $existing, $write, $report);
                $this->copyLines($variation, $product, $existing, $write, $report);

                continue;
            }

            $name = filled($product->unit) ? trim((string) $product->unit) : 'piece';
            $precision = self::precisionFor($name);

            if (! in_array(strtolower($name), [...self::WHOLE, ...self::FRACTIONAL], true)) {
                $report['precision_unlisted'][] = "{$product->name}: unit \"{$name}\" takes {$precision} decimals";
            }

            if ($product->has_variations && $variation->is_default && filled($product->barcode)) {
                $report['unassigned'][] = "{$product->name}: {$product->barcode}";
                if ($write) {
                    UnassignedBarcode::query()->firstOrCreate(['product_id' => $product->id, 'barcode' => (string) $product->barcode]);
                }
            }

            $barcode = $this->barcodeFor($variation, $product);

            if ($barcode !== null && isset($taken[$barcode])) {
                $report['clashes'][] = "{$barcode}: {$product->name} ({$variation->name})";
            }

            $this->listDifferences($variation, $product, $report);

            $base = null;
            if ($write) {
                $base = ProductUnit::query()->create([
                    'variation_id' => $variation->id,
                    'name' => $name,
                    'factor' => '1.000',
                    'precision' => $precision,
                    'barcode' => $barcode,
                    'selling_price' => $variation->selling_price,
                    'wholesale_price' => $variation->wholesale_price,
                    'is_base' => true,
                    'can_sell' => true,
                    'can_buy' => true,
                    'is_default_purchase' => true,
                    'sort_order' => 0,
                ]);
            }

            if ($barcode !== null) {
                $taken[$barcode] = true;
            }
            $report['units']++;

            $this->copyShopFacts($variation, $product, $base, $write, $report);
            $this->copyLines($variation, $product, $base, $write, $report);
        }

        return $report;
    }

    /**
     * The barcode the base unit takes: the variation's own, else (a simple product's default
     * variation only) the product's. A product with real variations keeps its barcode waiting.
     */
    private function barcodeFor(ProductVariation $variation, Product $product): ?string
    {
        if (filled($variation->barcode)) {
            return (string) $variation->barcode;
        }

        if ($variation->is_default && ! $product->has_variations && filled($product->barcode)) {
            return (string) $product->barcode;
        }

        return null;
    }

    /**
     * The differences the copy resolves by keeping the variation's figure, listed for a person.
     *
     * @param  array<string, mixed>  $report
     */
    private function listDifferences(ProductVariation $variation, Product $product, array &$report): void
    {
        if ($variation->is_default && $product->selling_price !== null && $variation->selling_price !== null
            && bccomp((string) $product->selling_price, (string) $variation->selling_price, 2) !== 0) {
            $report['differences'][] = "{$product->name}: selling price {$product->selling_price} on the product, {$variation->selling_price} on its variation (the variation's is kept)";
        }

        if ($variation->is_default && $product->cost_price !== null && $variation->cost_price !== null
            && bccomp((string) $product->cost_price, (string) $variation->cost_price, 4) !== 0) {
            $report['differences'][] = "{$product->name}: cost {$product->cost_price} on the product, {$variation->cost_price} on its variation (the variation's is kept)";
        }
    }

    /**
     * Shop price overrides into `shop_unit_prices` for the base unit, and the shop facts that move
     * from `shop_product` into `shop_variation` where the latter is empty.
     *
     * @param  array<string, mixed>  $report
     */
    private function copyShopFacts(ProductVariation $variation, Product $product, ?ProductUnit $base, bool $write, array &$report): void
    {
        foreach (ShopVariation::query()->where('variation_id', $variation->id)->get() as $stock) {
            $pivot = $variation->is_default ? $product->shops->firstWhere('id', $stock->shop_id)?->pivot : null;

            $selling = $stock->selling_price ?? $pivot?->selling_price;
            $wholesale = $stock->wholesale_price ?? $pivot?->wholesale_price;

            if ($pivot !== null && $stock->selling_price !== null && $pivot->selling_price !== null
                && bccomp((string) $stock->selling_price, (string) $pivot->selling_price, 2) !== 0) {
                $report['differences'][] = "{$product->name} at shop {$stock->shop_id}: {$pivot->selling_price} on the shop's product, {$stock->selling_price} on its variation (the variation's is kept)";
            }

            // A dry run has no base unit yet, so it counts what a write would create.
            $copied = $base !== null && ShopUnitPrice::query()->where('shop_id', $stock->shop_id)->where('unit_id', $base->id)->exists();

            if (($selling !== null || $wholesale !== null) && $variation->is_default && ! $copied) {
                $report['shop_prices']++;

                if ($write && $base !== null) {
                    ShopUnitPrice::query()->updateOrCreate(
                        ['shop_id' => $stock->shop_id, 'unit_id' => $base->id],
                        ['selling_price' => $selling, 'wholesale_price' => $wholesale],
                    );
                }
            }

            if ($pivot !== null && $stock->cost_price === null && $pivot->cost_price !== null) {
                $report['costs']++;
                if ($write) {
                    $stock->forceFill(['cost_price' => $pivot->cost_price])->save();
                }
            }

            if ($pivot !== null && $stock->reorder_level === null && $pivot->reorder_level !== null
                && (int) $pivot->reorder_level !== (int) $product->reorder_level) {
                $report['reorder_levels']++;
                if ($write) {
                    $stock->forceFill(['reorder_level' => $pivot->reorder_level])->save();
                }
            }
        }

        if ($variation->is_default && $variation->cost_price === null && $product->cost_price !== null) {
            $report['costs']++;
            if ($write) {
                $variation->forceFill(['cost_price' => $product->cost_price])->save();
            }
        }
    }

    /**
     * Sale lines get their base unit and base quantity (stage 11, step 3 wrote both for new lines
     * only). A return line has no variation, so it is copied only for a product without real
     * variations; any other return line is counted for a person to look at.
     *
     * @param  array<string, mixed>  $report
     */
    private function copyLines(ProductVariation $variation, Product $product, ?ProductUnit $base, bool $write, array &$report): void
    {
        $sales = SaleItem::query()->whereNull('unit_id')->where(function ($query) use ($variation, $product): void {
            $query->where('variation_id', $variation->id);

            if (! $product->has_variations && $variation->is_default) {
                $query->orWhere(fn ($simple) => $simple->whereNull('variation_id')->where('product_id', $product->id));
            }
        });
        $report['lines'] += (clone $sales)->count();

        if ($write && $base !== null) {
            $sales->update(['unit_id' => $base->id, 'base_quantity' => DB::raw('quantity')]);
        }

        if (! $variation->is_default) {
            return;
        }

        if (! $product->has_variations) {
            $returns = ReturnItem::query()->whereNull('unit_id')->where('product_id', $product->id);
            $report['lines'] += (clone $returns)->count();

            if ($write && $base !== null) {
                $returns->update(['unit_id' => $base->id, 'base_quantity' => DB::raw('quantity')]);
            }

            return;
        }

        $report['unresolved_returns'] += ReturnItem::query()->whereNull('unit_id')->where('product_id', $product->id)->count();
    }

    /**
     * The decimals a unit name allows: whole pieces, or weights and volumes; anything else is whole.
     */
    public static function precisionFor(string $name): int
    {
        return in_array(strtolower(trim($name)), self::FRACTIONAL, true) ? 3 : 0;
    }
}
