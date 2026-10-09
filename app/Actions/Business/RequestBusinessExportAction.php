<?php

namespace App\Actions\Business;

use App\Jobs\BuildBusinessExportJob;
use App\Models\Business;
use App\Models\BusinessExport;
use App\Models\User;

class RequestBusinessExportAction
{
    /**
     * Minutes a pending export blocks a new request. After that it is
     * presumed stuck (e.g. no queue worker) and the owner may ask again.
     */
    public const PENDING_MINUTES = 30;

    /**
     * Queues an export of everything the business holds; the owner is emailed
     * a link when it is ready. Returns the export already being prepared
     * instead of starting a second one.
     */
    public function execute(Business $business, User $requester, bool $final = false): BusinessExport
    {
        $pending = $business->exports()
            ->where('is_final', $final)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(self::PENDING_MINUTES))
            ->first();

        if ($pending) {
            return $pending;
        }

        $export = $business->exports()->create([
            'requested_by' => $requester->id,
            'is_final' => $final,
        ]);

        BuildBusinessExportJob::dispatch($export->id)->afterCommit();

        return $export;
    }
}
