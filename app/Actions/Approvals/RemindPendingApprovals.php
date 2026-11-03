<?php

namespace App\Actions\Approvals;

use App\Models\StockAdjustment;
use App\Support\Approvals\ApprovalInbox;
use App\Support\Stock\AdjustmentApproval;
use App\Support\Tenancy\TenantContext;

/**
 * Requests still pending after 24 hours: their eligible approvers get one
 * reminder (stage 10, decided 3 November 2026); the owner's dashboard lists
 * them until they are settled. Nothing is applied or rejected here.
 */
class RemindPendingApprovals
{
    public const AFTER_HOURS = 24;

    public function __construct(
        private TenantContext $tenant,
        private ApprovalInbox $inbox,
        private AdjustmentApproval $adjustments,
    ) {}

    /**
     * @return int the requests reminded about
     */
    public function execute(): int
    {
        $reminded = 0;

        $this->tenant->eachBusiness(function () use (&$reminded): void {
            StockAdjustment::query()->pending()->whereNull('reminded_at')
                ->where('created_at', '<=', now()->subHours(self::AFTER_HOURS))
                ->with(['shop', 'creator'])->orderBy('id')
                ->each(function (StockAdjustment $adjustment) use (&$reminded): void {
                    $this->inbox->request($this->adjustments->subject($adjustment), $this->adjustments->approversOf($adjustment), reminder: true);
                    $adjustment->forceFill(['reminded_at' => now()])->save();
                    $reminded++;
                });
        });

        return $reminded;
    }
}
