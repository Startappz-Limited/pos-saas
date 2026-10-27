<?php

namespace App\Services;

use App\Models\Register;
use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Issues sequential invoice numbers.
 *
 * Sales used to be numbered `INV-` plus eight random characters. That is not a
 * serial number: a tax invoice has to come from a gapless sequence so the
 * series can be reconciled, and a random string on a unique column will
 * eventually collide and fail a sale at the till.
 *
 * Since stage 1b each register (till) has its own series (decision 4.9), so a
 * till can number its sales without asking any other till:
 * `INV-{shop code}-{register code}-{year}-{sequence}`. Numbers are unique per
 * business: shop codes are unique within a business and register codes within
 * a shop.
 *
 * The counter lives in `invoice_sequences`, one row per shop and series (the
 * register plus the period). The row is taken with `lockForUpdate()` so two
 * sales on one register issued at the same moment queue rather than both
 * reading the same number; the unique key on (shop_id, series) and an upsert
 * cover the race to create the row in the first place.
 */
class InvoiceNumberService
{
    /**
     * Reserve and return the next invoice number for a register of a shop.
     *
     * Must be called inside the transaction that creates the sale, so that an
     * abandoned sale does not burn a number. With no register named, the
     * shop's default register numbers the sale (decision 4.8).
     *
     * The shop and register are the caller's, so they are read by id, whatever
     * the viewer's shop access.
     */
    public function next(Shop|int|null $shop, Register|int|null $register = null): string
    {
        $shopId = $shop instanceof Shop ? $shop->id : $shop;
        $period = $this->period();

        if ($shopId === null) {
            // Nothing to key a sequence on; fall back to a timestamped number
            // rather than failing the sale outright.
            return $this->format(null, null, $period, (int) now()->format('Hisu'));
        }

        $registerId = $register instanceof Register ? $register->id : $register;
        $registerId ??= Register::defaultFor($shopId)->id;

        $number = $this->claim($shopId, $registerId, self::series($registerId, $period));

        return $this->format(
            DB::table('shops')->where('id', $shopId)->value('code'),
            DB::table('registers')->where('id', $registerId)->value('code'),
            $period,
            $number,
        );
    }

    /**
     * Takes a number a till gave a sale while offline (Phase 7): it must be in
     * that register's own series for the year it was recorded,
     * `INV-{shop}-{register}-{year}-{n}`. The series then counts on past it,
     * so numbers issued online never repeat one. Returns n, the number the
     * series expected next (anything between is a gap), and the number's prefix
     * and period, so the caller can flag gaps (4.9).
     *
     * Call inside the transaction that creates the sale. A number already
     * used in the business is refused by the sales table's unique key.
     *
     * @throws \InvalidArgumentException when it isn't this register's series
     */
    /**
     * @return array{number: int, expected: int, prefix: string, period: string}
     */
    public function adopt(int $shopId, int $registerId, string $invoiceNumber, CarbonInterface $recordedAt): array
    {
        $period = $this->period($recordedAt);
        $number = (int) (preg_match('/(\d+)$/', $invoiceNumber, $match) ? $match[1] : 0);
        $expected = $this->format(
            DB::table('shops')->where('id', $shopId)->value('code'),
            DB::table('registers')->where('id', $registerId)->value('code'),
            $period,
            $number,
        );

        if ($number < 1 || $expected !== $invoiceNumber) {
            throw new \InvalidArgumentException("{$invoiceNumber} is not in this till's series (expected the form {$expected}).");
        }

        $series = self::series($registerId, $period);
        $sequence = DB::table('invoice_sequences')->where('shop_id', $shopId)->where('series', $series);

        if (! (clone $sequence)->exists()) {
            DB::table('invoice_sequences')->upsert(
                [['shop_id' => $shopId, 'series' => $series, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]],
                ['shop_id', 'series'],
                ['updated_at'],
            );
        }

        $row = (clone $sequence)->lockForUpdate()->first();
        $expected = (int) $row->next_number;
        if ($number >= $expected) {
            DB::table('invoice_sequences')->where('id', $row->id)->update(['next_number' => $number + 1, 'updated_at' => now()]);
            DB::table('registers')->where('id', $registerId)->where('last_invoice_number', '<', $number)->update(['last_invoice_number' => $number]);
        }

        return [
            'number' => $number,
            'expected' => $expected,
            'prefix' => substr($invoiceNumber, 0, -strlen((string) $match[1])),
            'period' => $period,
        ];
    }

    /**
     * The sequence key of a register in a period. Keyed on the register's id,
     * not its code, so renaming a till does not restart its series.
     */
    public static function series(int $registerId, string $period): string
    {
        return "r{$registerId}:{$period}";
    }

    /**
     * Atomically take the next number in a series, and note it on the register.
     *
     * The series row is created before it is locked, never looked for under a
     * lock: on MySQL a locking read of a missing row takes a gap lock, and two
     * registers both doing that and then inserting into the gap deadlock,
     * rolling one sale back. The upsert takes an exclusive lock even when the
     * row already exists (INSERT IGNORE would take a shared one, which two
     * claimers deadlock upgrading), and nothing here swallows a database
     * error, so a failure rolls the caller's sale back whole.
     */
    private function claim(int $shopId, int $registerId, string $series): int
    {
        $sequence = DB::table('invoice_sequences')->where('shop_id', $shopId)->where('series', $series);

        if (! (clone $sequence)->exists()) {
            DB::table('invoice_sequences')->upsert(
                [['shop_id' => $shopId, 'series' => $series, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]],
                ['shop_id', 'series'],
                ['updated_at'],
            );
        }

        $row = (clone $sequence)->lockForUpdate()->first()
            ?? throw new \RuntimeException("Could not allocate an invoice number for register {$registerId}.");

        DB::table('invoice_sequences')->where('id', $row->id)->update([
            'next_number' => $row->next_number + 1,
            'updated_at' => now(),
        ]);

        return $this->noted($registerId, (int) $row->next_number);
    }

    /**
     * Record the number as the last one the register issued (in the same
     * transaction, under the series lock), and return it.
     */
    private function noted(int $registerId, int $number): int
    {
        DB::table('registers')->where('id', $registerId)->update(['last_invoice_number' => $number]);

        return $number;
    }

    /**
     * The period: the calendar year for a yearly reset, "*" otherwise.
     */
    private function period(?CarbonInterface $at = null): string
    {
        return config('invoicing.reset', 'yearly') === 'yearly'
            ? ($at ?? now())->format('Y')
            : '*';
    }

    private function format(?string $shopCode, ?string $registerCode, string $period, int $number): string
    {
        $separator = (string) config('invoicing.separator', '-');

        // The business's own prefix (stage 3), else the platform's.
        $parts = [(string) (current_business()?->invoice_prefix ?: config('invoicing.prefix', 'INV'))];

        if (config('invoicing.include_shop_code', true) && $shopCode) {
            $parts[] = $this->code($shopCode, 'SHOP');
        }

        if ($registerCode) {
            $parts[] = $this->code($registerCode, 'REG');
        }

        if ($period !== '*') {
            $parts[] = $period;
        }

        $parts[] = str_pad((string) $number, (int) config('invoicing.pad', 6), '0', STR_PAD_LEFT);

        return implode($separator, $parts);
    }

    private function code(string $code, string $fallback): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?: $fallback);
    }
}
