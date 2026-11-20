<?php

namespace App\Support\Stock;

use App\Casts\Quantity;
use App\Models\ProductUnit;
use App\Models\ProductVariation;

/**
 * Shows a base quantity in the largest sensible unit plus the remainder
 * (stage 11, step 2): "12 Crate + 5 Bottle (293 Bottle)"; "3 Bag + 12.5 kg
 * (162.5 kg)". Stock screens, low-stock alerts and the stock value report use
 * this instead of a bare base figure.
 */
class UnitBreakdown
{
    public static function describe(ProductVariation $variation, int|float|string $baseQuantity): string
    {
        $quantity = Quantity::normalize($baseQuantity);
        $negative = bccomp($quantity, '0', 3) < 0;
        $abs = $negative ? bcmul($quantity, '-1', 3) : $quantity;

        $base = ProductUnit::query()->where('variation_id', $variation->id)->where('is_base', true)->first();
        $basePrecision = $base?->precision ?? 0;
        $baseName = $base?->name ?? 'unit';
        $baseLabel = self::format($abs, $basePrecision, $baseName);

        $pack = self::largestPack($variation, $abs);
        if ($pack === null) {
            return ($negative ? '-' : '').$baseLabel;
        }

        $packs = bcdiv($abs, (string) $pack->factor, 0);
        $remainder = bcsub($abs, bcmul($packs, (string) $pack->factor, 3), 3);
        $packsLabel = "{$packs} {$pack->name}";
        $remainderLabel = bccomp($remainder, '0', 3) > 0 ? ' + '.self::format($remainder, $basePrecision, $baseName) : '';

        return ($negative ? '-' : '')."{$packsLabel}{$remainderLabel} ({$baseLabel})";
    }

    /**
     * The largest sellable or buyable unit with a whole-number factor at or
     * below $quantity, or null when none fits.
     */
    private static function largestPack(ProductVariation $variation, string $quantity): ?ProductUnit
    {
        return ProductUnit::query()->where('variation_id', $variation->id)->where('is_base', false)
            ->where(fn ($query) => $query->where('can_sell', true)->orWhere('can_buy', true))
            ->get()
            ->filter(fn (ProductUnit $unit): bool => bccomp(bcadd((string) $unit->factor, '0', 0), (string) $unit->factor, 10) === 0)
            ->filter(fn (ProductUnit $unit): bool => bccomp((string) $unit->factor, $quantity, 3) <= 0)
            ->sortByDesc(fn (ProductUnit $unit): float => (float) $unit->factor)
            ->first();
    }

    /**
     * A quantity at a unit's precision, trimmed of trailing zeros ("12.5 kg",
     * not "12.500 kg"; "5 Bottle", not "5.000 Bottle").
     */
    private static function format(string $quantity, int $precision, string $name): string
    {
        $formatted = number_format((float) $quantity, $precision, '.', '');
        if ($precision > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return "{$formatted} {$name}";
    }
}
