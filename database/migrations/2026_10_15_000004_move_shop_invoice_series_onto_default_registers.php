<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Invoice numbers per register (stage 1b, decision 4.9). A shop's invoice
 * series used to be keyed on the period alone ("2026", or "*" for a series
 * that never resets); each register now has its own, keyed "r{register}:{period}".
 *
 * Every existing shop series becomes its default register's series, so the
 * shop's count carries on at that till rather than restarting at 1. The
 * numbers already issued keep their old form (INV-NRB-2026-000123); new ones
 * read INV-NRB-R1-2026-000124, so the two never collide.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacy = DB::table('invoice_sequences')->where('series', 'not like', 'r%:%')->get();

            foreach ($legacy as $row) {
                $registerId = DB::table('registers')->where('shop_id', $row->shop_id)->where('code', 'R1')->value('id');

                if ($registerId === null) {
                    throw new RuntimeException("Shop {$row->shop_id} has no default register to carry its invoice series.");
                }

                DB::table('invoice_sequences')->where('id', $row->id)->update(['series' => "r{$registerId}:{$row->series}"]);

                $last = DB::table('registers')->where('id', $registerId)->value('last_invoice_number');
                if ($row->series === $this->currentPeriod() || (int) $last === 0) {
                    DB::table('registers')->where('id', $registerId)->update(['last_invoice_number' => max(0, $row->next_number - 1)]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $defaults = DB::table('registers')->where('code', 'R1')->pluck('id');

            foreach ($defaults as $registerId) {
                $prefix = "r{$registerId}:";

                foreach (DB::table('invoice_sequences')->where('series', 'like', $prefix.'%')->get() as $row) {
                    DB::table('invoice_sequences')->where('id', $row->id)->update(['series' => substr($row->series, strlen($prefix))]);
                }
            }

            // Other registers' series have no per-shop form to return to; the
            // per-shop counter takes over from its own row again.
        });
    }

    private function currentPeriod(): string
    {
        return config('invoicing.reset', 'yearly') === 'yearly' ? now()->format('Y') : '*';
    }
};
