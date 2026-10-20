<?php

namespace App\Actions\Business;

use App\Jobs\BuildBusinessExport;
use App\Models\Business;
use App\Models\BusinessClosure;
use App\Models\BusinessExport;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * The business-closure flow (decision 4.9, stage 3).
 *
 * - start(): the business becomes `closing` (nobody can work in it, and it is
 *   never opened), its final export is built and emailed to its owners, and
 *   erasure is scheduled after the grace period. Called by an owner after
 *   confirming the emailed code, or by billing after 30 business days of
 *   lapse (Phase 4, reason `lapsed`).
 * - cancel(): during the grace period, an owner reopens the business.
 * - erase(): after the grace period (the daily `businesses:erase-closed`),
 *   every row, audit entry and file of the business goes (PurgeBusiness).
 */
class CloseBusiness
{
    public function __construct(private TenantContext $tenant, private PurgeBusiness $purge) {}

    public function start(Business $business, ?User $requester, string $reason = BusinessClosure::REASON_OWNER): BusinessClosure
    {
        $closure = DB::transaction(function () use ($business, $requester, $reason): BusinessClosure {
            $business->update(['status' => Business::STATUS_CLOSING]);

            return BusinessClosure::create([
                'business_id' => $business->id,
                'business_uuid' => $business->uuid,
                'business_name' => $business->name,
                'requested_by' => $requester?->id,
                'reason' => $reason,
                'delete_after' => now()->addDays(self::graceDays()),
            ]);
        });

        $this->tenant->run($business, function () use ($requester): void {
            $export = BusinessExport::create(['requested_by' => $requester?->id, 'purpose' => BusinessExport::PURPOSE_CLOSURE]);
            BuildBusinessExport::dispatch($export->id);
        });

        return $closure;
    }

    public function cancel(BusinessClosure $closure, ?User $owner): void
    {
        DB::transaction(function () use ($closure, $owner): void {
            $closure->update(['cancelled_at' => now(), 'cancelled_by' => $owner?->id]);
            Business::query()->whereKey($closure->business_id)->update(['status' => Business::STATUS_ACTIVE]);
        });
    }

    /**
     * Erases the business of a closure whose grace period is over.
     */
    public function erase(BusinessClosure $closure): void
    {
        $business = Business::query()->find($closure->business_id);

        if ($business === null || $business->status !== Business::STATUS_CLOSING || $closure->delete_after->isFuture()) {
            return;
        }

        $summary = $this->purge->execute($business);

        $closure->update(['erased_at' => now(), 'summary' => $summary]);
    }

    public static function graceDays(): int
    {
        return max(1, (int) config('tenancy.closure_grace_days', 30));
    }
}
