<?php

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy\BusinessData;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Erases a business at the end of its closure (stage 3): every row of it in
 * the main database (soft-deleted ones included: nothing is kept), the logins
 * that belong to it alone, its rows in the audit database and its files on
 * both disks. Logins that are also in another business stay, without their
 * membership here.
 *
 * Only ever called by the closure flow, after the grace period, for a business
 * whose final export was offered to its owners. There is no undo.
 */
class PurgeBusiness
{
    private const CHUNK = 500;

    /**
     * @return array<string, int> rows deleted per table
     */
    public function execute(Business $business): array
    {
        $data = new BusinessData($business->id);
        $owned = $data->ownedIds();
        $users = $data->ownedUserIds();
        $emails = DB::table('users')->whereIn('id', $users)->pluck('email')->all();
        $deleted = [];

        DB::transaction(function () use ($data, $owned, $users, $emails, $business, &$deleted): void {
            foreach ($data->tablesChildrenFirst() as $table) {
                $deleted[$table] = match (true) {
                    in_array($table, ['businesses', 'users'], true) => 0,
                    $table === 'personal_access_tokens' => DB::table($table)->where('business_id', $business->id)
                        ->orWhere(fn ($query) => $query->where('tokenable_type', (new User)->getMorphClass())->whereIn('tokenable_id', $users))
                        ->delete(),
                    $table === 'sessions' => DB::table($table)->whereIn('user_id', $users)->delete(),
                    $table === 'password_reset_tokens' => DB::table($table)->whereIn('email', $emails)->delete(),
                    $data->hasId($table) => $this->deleteIds($data, $table, $owned[$table] ?? []),
                    default => $data->rowsOf($table, [...$owned, 'users' => $users])->delete(),
                };
            }

            // Other logins no longer open it by default.
            DB::table('users')->where('default_business_id', $business->id)->update(['default_business_id' => null]);

            foreach (array_chunk($users, self::CHUNK) as $ids) {
                $deleted['users'] = ($deleted['users'] ?? 0) + DB::table('users')->whereIn('id', $ids)->delete();
            }

            $deleted['businesses'] = DB::table('businesses')->where('id', $business->id)->delete();
        });

        // Separate databases and disks: after the main rows are gone, so a
        // failure here leaves nothing readable behind in the application.
        $deleted['audit_logs'] = DB::connection('audit')->table('audit_logs')->where('business_id', $business->id)->delete();

        foreach ([TenantFiles::PRIVATE_DISK, TenantFiles::PUBLIC_DISK] as $disk) {
            Storage::disk($disk)->deleteDirectory((string) $business->uuid);
        }

        return array_filter($deleted);
    }

    /**
     * @param  list<int|string>  $ids
     */
    private function deleteIds(BusinessData $data, string $table, array $ids): int
    {
        $count = 0;

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            foreach ($data->selfReferences($table) as $column) {
                DB::table($table)->whereIn('id', $chunk)->update([$column => null]);
            }
        }

        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            $count += DB::table($table)->whereIn('id', $chunk)->delete();
        }

        return $count;
    }
}
