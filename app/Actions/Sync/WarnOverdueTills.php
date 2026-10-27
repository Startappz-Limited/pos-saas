<?php

namespace App\Actions\Sync;

use App\Actions\Business\CopyRoleTemplates;
use App\Models\Business;
use App\Models\Register;
use App\Models\User;
use App\Notifications\TillSyncOverdue;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;

/**
 * Warns owners about tills that haven't synced for `sync.overdue_hours`
 * (Phase 7, decision 4.5): once per silence, for every business that runs
 * background work. Nothing is blocked.
 */
class WarnOverdueTills
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @return int the tills warned about
     */
    public function execute(): int
    {
        $warned = 0;

        $this->tenant->eachBusiness(function (Business $business) use (&$warned): void {
            $tills = Register::query()->syncOverdue()->whereNull('sync_overdue_warned_at')->with('shop:id,name')->get();
            if ($tills->isEmpty()) {
                return;
            }

            $owners = User::query()->inCurrentBusiness()->role(CopyRoleTemplates::OWNER_ROLE)->get();

            foreach ($tills as $till) {
                Notification::send($owners, new TillSyncOverdue($business->name, (string) $till->shop?->name, $till->name, $till->code, $till->last_synced_at));
                $till->forceFill(['sync_overdue_warned_at' => now()])->saveQuietly();
                $warned++;
            }
        });

        return $warned;
    }
}
