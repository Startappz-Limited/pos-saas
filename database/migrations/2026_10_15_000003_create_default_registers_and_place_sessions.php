<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers (stage 1b, decision 4.2): every shop gets its default register
 * ("R1", "Main till"), and every existing cash session is placed on its shop's
 * default register. From here on a session always runs on a register.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (DB::table('shops')->pluck('id') as $shopId) {
                DB::table('registers')->insertOrIgnore([
                    'uuid' => (string) Str::uuid(),
                    'shop_id' => $shopId,
                    'name' => 'Main till',
                    'code' => 'R1',
                    'last_invoice_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $registerId = DB::table('registers')->where('shop_id', $shopId)->where('code', 'R1')->value('id');
                DB::table('cash_registers')->where('shop_id', $shopId)->whereNull('register_id')->update(['register_id' => $registerId]);
            }

            $unplaced = DB::table('cash_registers')->whereNull('register_id')->count();
            if ($unplaced > 0) {
                throw new RuntimeException("{$unplaced} cash session(s) could not be placed on a register.");
            }
        });
    }

    public function down(): void
    {
        // Sessions keep their register; the registers stay (nothing to undo
        // that would lose data).
    }
};
