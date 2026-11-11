<?php

namespace App\Support\Sync;

use App\Models\Register;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\SyncIssue;
use App\Support\Approvals\Flags;
use App\Support\Stock\Expiry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Records what a till's push turned up (Phase 7, decision 4.9), once per
 * thing, for the review screen.
 */
class SyncIssues
{
    /** A gap longer than this is recorded as a range only, and not tracked number by number. */
    private const TRACKED_GAP = 1000;

    /**
     * @param  array<string, mixed>  $details
     */
    public function record(string $type, string $key, array $details, ?Register $register = null, ?int $shopId = null): SyncIssue
    {
        return SyncIssue::query()->firstOrCreate(
            ['type' => $type, 'key' => $key],
            ['details' => $details, 'register_id' => $register?->id, 'shop_id' => $register?->shop_id ?? $shopId],
        );
    }

    /**
     * Records a sale line that took its shop below zero (the ledger let it
     * through and costed it provisionally), once per sale, shop and
     * variation, for the review screen: from a till's push, an online order or
     * abandoned-cart conversion (always allowed), and a web or flat API sale
     * pushed through with sales.oversell (stage 11 step 4 follow-up, decided 5
     * November 2026 — replaces "selling below zero stays allowed" with a
     * policy that refuses the web and flat API by default). The owner is
     * notified the first time one is recorded; it self-resolves
     * (resolveNegativeStock()) once the shop's stock for that variation
     * returns to zero or above, rather than waiting for someone to check it.
     *
     * @param  array<string, mixed>  $details
     * @param  array{by: string, reason: string}|null  $override  who pushed it through and why, when sales.oversell was used
     * @return bool whether any line went below zero
     */
    public function recordNegativeStock(Sale $sale, ?Register $register = null, array $details = [], ?array $override = null): bool
    {
        $negative = $this->negativeStockPairs($sale);

        if ($negative->isEmpty()) {
            return false;
        }

        foreach ($negative as $movement) {
            $shopId = (int) $movement->shop_id;
            $variationId = (int) $movement->variation_id;

            $issue = $this->record(
                SyncIssue::NEGATIVE_STOCK,
                "sale:{$sale->uuid}:{$shopId}:{$variationId}",
                [
                    'sale' => $sale->uuid, 'invoice_number' => $sale->invoice_number,
                    'shop_id' => $shopId, 'variation_id' => $variationId,
                    ...($override !== null ? ['overridden_by' => $override['by'], 'reason' => $override['reason']] : []),
                    ...$details,
                ],
                $register,
                $shopId,
            );

            if ($issue->wasRecentlyCreated) {
                app(Flags::class)->raise(
                    $sale,
                    $shopId,
                    (int) $sale->business_id,
                    "negative_stock:{$sale->uuid}:{$shopId}:{$variationId}",
                    __('Sold below zero stock'),
                    $override !== null
                        ? __(':invoice sold below zero stock at this shop, pushed through by :by: :reason', ['invoice' => $sale->invoice_number, 'by' => $override['by'], 'reason' => $override['reason']])
                        : __(':invoice sold below zero stock at this shop.', ['invoice' => $sale->invoice_number]),
                    route('sync-issues.index'),
                    notifyOwners: true,
                );
            }
        }

        return true;
    }

    /**
     * Records a sale line that went short because sellable stock existed
     * only as expired batches (stage 11, step 5), once per sale, shop and
     * variation. Found from the provisional cost_allocations consume()
     * writes whenever a line's need outlives its eligible (unexpired)
     * layers — not from the shop going below zero: a shop can hold plenty
     * of *expired* stock and still show a positive total after the sale,
     * since shop_variation counts physical units whatever their expiry.
     *
     * @return bool whether any line went short on expired stock
     */
    public function recordExpiredStock(Sale $sale, ?Register $register = null): bool
    {
        $itemIds = $sale->items()->pluck('id');
        $provisional = DB::table('cost_allocations')->where('is_provisional', true)->whereIn('sale_item_id', $itemIds)
            ->get(['shop_id', 'variation_id'])->unique(fn ($row): string => "{$row->shop_id}:{$row->variation_id}");
        $any = false;

        foreach ($provisional as $row) {
            $shopId = (int) $row->shop_id;
            $variationId = (int) $row->variation_id;
            $shop = Shop::query()->find($shopId);

            if ($shop === null || ! $this->hasExpiredStock($shopId, $variationId, $shop)) {
                continue;
            }

            $any = true;
            $this->record(
                SyncIssue::EXPIRED_STOCK,
                "expired:{$sale->uuid}:{$shopId}:{$variationId}",
                ['sale' => $sale->uuid, 'invoice_number' => $sale->invoice_number, 'shop_id' => $shopId, 'variation_id' => $variationId],
                $register,
                $shopId,
            );
        }

        return $any;
    }

    /**
     * The distinct (shop, variation) pairs a sale's stock movements took
     * below zero, once each.
     *
     * @return Collection<int, StockMovement>
     */
    private function negativeStockPairs(Sale $sale): Collection
    {
        return StockMovement::query()->where('reference_uuid', $sale->uuid)->where('quantity_after', '<', 0)
            ->get(['shop_id', 'variation_id'])->unique(fn (StockMovement $movement): string => "{$movement->shop_id}:{$movement->variation_id}");
    }

    private function hasExpiredStock(int $shopId, int $variationId, Shop $shop): bool
    {
        $boundary = Expiry::firstSellableDate($shop)->toDateString();

        return DB::table('cost_layers')->where('shop_id', $shopId)->where('variation_id', $variationId)
            ->where('remaining_quantity', '>', 0)->whereNotNull('expiry_date')->where('expiry_date', '<', $boundary)->exists();
    }

    /**
     * Resolves every open negative_stock and expired_stock issue for
     * $shopId and $variationId (stage 11, step 4 follow-up and step 5): the
     * shop's stock for that variation is back at zero or above, so whatever
     * oversold or ran it short of unexpired stock is no longer a concern.
     * Resolved one row at a time (not a bulk update) so each resolution is
     * still captured by SyncIssue's own audit trail.
     */
    public function resolveNegativeStock(int $shopId, int $variationId): void
    {
        SyncIssue::query()->open()->whereIn('type', [SyncIssue::NEGATIVE_STOCK, SyncIssue::EXPIRED_STOCK])->where('shop_id', $shopId)
            ->where('details->variation_id', $variationId)
            ->get()
            ->each(fn (SyncIssue $issue) => $issue->update(['resolved_at' => now(), 'resolution' => 'self_resolved']));
    }

    /**
     * A till's invoice number was taken into its series (decision 4.9): a
     * number past the next expected leaves a gap; a number inside a gap fills
     * it, and a gap with nothing missing any more resolves itself.
     *
     * @param  array{number: int, expected: int, prefix: string, period: string}  $adoption
     */
    public function invoiceAdopted(Register $register, array $adoption): void
    {
        ['number' => $number, 'expected' => $expected, 'prefix' => $prefix, 'period' => $period] = $adoption;

        if ($number > $expected) {
            $from = $expected;
            $to = $number - 1;
            $this->record(SyncIssue::INVOICE_GAP, "{$register->id}:{$period}:{$from}-{$to}", [
                'from' => $from,
                'to' => $to,
                'prefix' => $prefix,
                'missing' => $to - $from + 1 <= self::TRACKED_GAP ? range($from, $to) : null,
            ], $register);

            return;
        }

        SyncIssue::query()->open()
            ->where('type', SyncIssue::INVOICE_GAP)
            ->where('register_id', $register->id)
            ->where('key', 'like', "{$register->id}:{$period}:%")
            ->get()
            ->each(function (SyncIssue $gap) use ($number): void {
                $missing = $gap->details['missing'] ?? null;
                if (! is_array($missing) || ! in_array($number, $missing, true)) {
                    return;
                }

                $missing = array_values(array_diff($missing, [$number]));
                $gap->update([
                    'details' => [...$gap->details, 'missing' => $missing],
                    ...($missing === [] ? ['resolved_at' => now(), 'resolution' => 'filled'] : []),
                ]);
            });
    }
}
