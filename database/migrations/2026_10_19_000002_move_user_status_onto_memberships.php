<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Status is per business (stage 3, decided 19 October 2026): suspending or
 * deactivating someone from a business's screens changes only their
 * membership; the account's own status is the platform operator's.
 *
 * Every inactive or suspended account was set so by its business's screens
 * before there were several businesses, so that status moves onto the
 * person's memberships and the account becomes active again. Otherwise a
 * business that later reactivates them couldn't, and only the platform
 * operator could let them back in. An already inactive membership (set by the
 * legacy import, or by removing the person) keeps its status.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $users = DB::table('users')->whereIn('status', ['inactive', 'suspended'])->get(['id', 'status']);

            foreach ($users as $user) {
                DB::table('business_user')
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->update(['status' => $user->status, 'updated_at' => now()]);

                // The import made these memberships inactive; a suspension stays a suspension.
                if ($user->status === 'suspended') {
                    DB::table('business_user')->where('user_id', $user->id)->where('status', 'inactive')
                        ->update(['status' => 'suspended', 'updated_at' => now()]);
                }

                DB::table('users')->where('id', $user->id)->update(['status' => 'active', 'updated_at' => now()]);
            }
        });
    }

    /**
     * Puts a status back on the account of anyone who has memberships and none
     * of them active; their memberships keep theirs.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            $blocked = DB::table('business_user')
                ->select('user_id', DB::raw("MAX(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended"))
                ->groupBy('user_id')
                ->havingRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) = 0")
                ->get();

            foreach ($blocked as $row) {
                DB::table('users')->where('id', $row->user_id)
                    ->update(['status' => $row->suspended ? 'suspended' : 'inactive', 'updated_at' => now()]);
            }
        });
    }
};
