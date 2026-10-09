<?php

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\User;
use App\Notifications\BusinessClosureScheduled;
use Illuminate\Support\Facades\DB;

class CloseBusinessAction
{
    public function __construct(private RequestBusinessExportAction $requestExport) {}

    /**
     * Freezes the business for the grace period: from now on only the owner
     * can sign in (EnsureBusinessIsOpen), and the data is deleted once
     * purge_after has passed. Builds a final export from the frozen data and
     * emails the owner. Nothing is deleted here, so cancelling restores
     * everything.
     */
    public function execute(Business $business, User $owner): Business
    {
        DB::transaction(function () use ($business, $owner): void {
            $business->update([
                'closing_requested_at' => now(),
                'purge_after' => now()->addDays(Business::GRACE_DAYS),
                'closing_requested_by' => $owner->id,
            ]);

            $this->requestExport->execute($business, $owner, final: true);
        });

        $owner->notify(new BusinessClosureScheduled($business));

        return $business;
    }
}
