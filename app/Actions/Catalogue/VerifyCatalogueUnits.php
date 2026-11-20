<?php

namespace App\Actions\Catalogue;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\ReturnItem;
use App\Models\SaleItem;
use App\Models\UnassignedBarcode;

/**
 * Checks that release 2 can drop the old catalogue columns without losing a value (stage 11,
 * step 7). Read-only. A gap is a value the drop would lose, or a line with no unit; a warning is
 * something a person must still do (a barcode waiting for assignment). Gaps fail the command.
 */
class VerifyCatalogueUnits
{
    /**
     * @return array{counts: array<string, int>, gaps: list<string>, warnings: list<string>}
     */
    public function run(): array
    {
        $gaps = [];
        $warnings = [];
        $counts = [];

        $variations = ProductVariation::query()->withoutGlobalScope(ProductVariation::HIDE_DEFAULT)
            ->with(['product' => fn ($query) => $query->withTrashed()])->get();
        $counts['variations'] = $variations->count();

        $withoutBase = 0;
        $priceGaps = 0;
        $barcodeGaps = 0;
        $costGaps = 0;

        foreach ($variations as $variation) {
            $base = ProductUnit::query()->withTrashed()->where('variation_id', $variation->id)->where('is_base', true)->first();

            if ($base === null) {
                $withoutBase++;

                continue;
            }

            if ($variation->selling_price !== null && bccomp((string) $base->selling_price, (string) $variation->selling_price, 2) !== 0) {
                $priceGaps++;
            }

            if (filled($variation->barcode) && $base->barcode !== $variation->barcode) {
                $barcodeGaps++;
            }

            if ($variation->is_default && $variation->product?->cost_price !== null && $variation->cost_price === null) {
                $costGaps++;
            }
        }

        $counts['variations_without_base_unit'] = $withoutBase;
        $counts['base_price_differences'] = $priceGaps;
        $counts['base_barcode_differences'] = $barcodeGaps;
        $counts['costs_not_moved'] = $costGaps;

        if ($withoutBase > 0) {
            $gaps[] = "{$withoutBase} variation(s) have no base unit: run catalogue:copy-to-units.";
        }
        if ($priceGaps > 0) {
            $gaps[] = "{$priceGaps} base unit(s) hold a selling price that differs from their variation's.";
        }
        if ($barcodeGaps > 0) {
            $gaps[] = "{$barcodeGaps} base unit(s) hold a barcode that differs from their variation's.";
        }
        if ($costGaps > 0) {
            $gaps[] = "{$costGaps} default variation(s) have no cost where their product has one.";
        }

        $lines = SaleItem::query()->where(fn ($query) => $query->whereNull('unit_id')->orWhereNull('base_quantity'))->count();
        $counts['sale_lines_without_unit'] = $lines;
        if ($lines > 0) {
            $gaps[] = "{$lines} sale line(s) have no unit or base quantity: run catalogue:copy-to-units.";
        }

        $returns = ReturnItem::query()->whereNull('unit_id')->count();
        $counts['return_lines_without_unit'] = $returns;
        if ($returns > 0) {
            $gaps[] = "{$returns} return line(s) have no unit (a return of a product with variations can't be placed without a person).";
        }

        // A product barcode on a product with real variations is either waiting or on a unit.
        $waiting = UnassignedBarcode::query()->pluck('barcode', 'product_id');
        $unitBarcodes = ProductUnit::query()->whereNotNull('barcode')->pluck('barcode')->flip();
        $loose = Product::query()->withTrashed()->where('has_variations', true)->whereNotNull('barcode')->get()
            ->filter(fn (Product $product): bool => ! isset($unitBarcodes[$product->barcode]) && ! $waiting->has($product->id));
        $counts['product_barcodes_unplaced'] = $loose->count();
        if ($loose->isNotEmpty()) {
            $gaps[] = "{$loose->count()} product barcode(s) on products with variations are neither on a unit nor waiting: run catalogue:copy-to-units.";
        }

        $counts['unassigned_barcodes'] = $waiting->count();
        foreach (UnassignedBarcode::query()->with('product:id,name')->get() as $row) {
            $warnings[] = "Barcode {$row->barcode} waits for assignment on ".($row->product?->name ?? "product {$row->product_id}").'.';
        }

        return ['counts' => $counts, 'gaps' => $gaps, 'warnings' => $warnings];
    }
}
