<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Issues sequential invoice numbers.
 *
 * Sales used to be numbered `INV-` plus eight random characters. That is not a
 * serial number: a tax invoice has to come from a gapless per-shop sequence so
 * the series can be reconciled, and a random string on a unique column will
 * eventually collide and fail a sale at the till.
 *
 * The counter lives in `invoice_sequences`, one row per shop and period. The row
 * is taken with `lockForUpdate()` so two registers issuing at the same moment
 * queue rather than both reading the same number; the unique key on
 * (shop_id, series) covers the race to create the row in the first place.
 */
class InvoiceNumberService
{
    /**
     * How many times to retry when a concurrent writer wins the race.
     */
    private const MAX_ATTEMPTS = 5;

    /**
     * Reserve and return the next invoice number for a shop.
     *
     * Must be called inside the transaction that creates the sale, so that an
     * abandoned sale does not burn a number.
     */
    public function next(Shop|int|null $shop): string
    {
        $shopId = $shop instanceof Shop ? $shop->id : $shop;

        if ($shopId === null) {
            // Nothing to key a sequence on; fall back to a timestamped number
            // rather than failing the sale outright.
            return $this->format(null, $this->series(), (int) now()->format('Hisu'));
        }

        $series = $this->series();
        $number = $this->claim($shopId, $series);

        $shopModel = $shop instanceof Shop ? $shop : Shop::find($shopId);

        return $this->format($shopModel, $series, $number);
    }

    /**
     * Atomically take the next number in a series.
     */
    private function claim(int $shopId, string $series): int
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $row = DB::table('invoice_sequences')
                ->where('shop_id', $shopId)
                ->where('series', $series)
                ->lockForUpdate()
                ->first();

            if ($row !== null) {
                DB::table('invoice_sequences')
                    ->where('id', $row->id)
                    ->update([
                        'next_number' => $row->next_number + 1,
                        'updated_at' => now(),
                    ]);

                return (int) $row->next_number;
            }

            try {
                DB::table('invoice_sequences')->insert([
                    'shop_id' => $shopId,
                    'series' => $series,
                    'next_number' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            } catch (QueryException $e) {
                // Another register created the row first — loop and read theirs.
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException("Could not allocate an invoice number for shop {$shopId}.");
    }

    /**
     * The sequence key: the calendar year for a yearly reset, "*" otherwise.
     */
    private function series(): string
    {
        return config('invoicing.reset', 'yearly') === 'yearly'
            ? now()->format('Y')
            : '*';
    }

    private function format(?Shop $shop, string $series, int $number): string
    {
        $separator = (string) config('invoicing.separator', '-');

        $parts = [(string) config('invoicing.prefix', 'INV')];

        if (config('invoicing.include_shop_code', true) && $shop?->code) {
            $parts[] = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $shop->code) ?: 'SHOP');
        }

        if ($series !== '*') {
            $parts[] = $series;
        }

        $parts[] = str_pad((string) $number, (int) config('invoicing.pad', 6), '0', STR_PAD_LEFT);

        return implode($separator, $parts);
    }
}
