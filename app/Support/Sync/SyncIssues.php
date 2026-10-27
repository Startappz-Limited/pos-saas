<?php

namespace App\Support\Sync;

use App\Models\Register;
use App\Models\SyncIssue;

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
    public function record(string $type, string $key, array $details, ?Register $register = null): SyncIssue
    {
        return SyncIssue::query()->firstOrCreate(
            ['type' => $type, 'key' => $key],
            ['details' => $details, 'register_id' => $register?->id, 'shop_id' => $register?->shop_id],
        );
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
