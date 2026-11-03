<?php

namespace App\Actions\Approvals;

use App\Models\SaleVoidRequest;
use App\Models\StockAdjustment;
use App\Support\Approvals\ApprovalInbox;
use App\Support\Sales\VoidRequests;
use App\Support\Stock\AdjustmentApproval;
use App\Support\Tenancy\TenantContext;

/**
 * Requests still pending after 24 hours: their eligible approvers get one
 * reminder (stage 10, decided 3 November 2026); the owner's dashboard lists
 * them until they are settled. Nothing is applied or rejected here; a void
 * request past its expiry (48 hours) is marked expired and its requester told.
 */
class RemindPendingApprovals
{
    public const AFTER_HOURS = 24;

    public function __construct(
        private TenantContext $tenant,
        private ApprovalInbox $inbox,
        private AdjustmentApproval $adjustments,
        private VoidRequests $voids,
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
                ->with(['shop', 'creator'])->lazyById()
                ->each(function (StockAdjustment $adjustment) use (&$reminded): void {
                    $this->inbox->request($this->adjustments->subject($adjustment), $this->adjustments->approversOf($adjustment), reminder: true);
                    $adjustment->forceFill(['reminded_at' => now()])->save();
                    $reminded++;
                });

            SaleVoidRequest::query()->pending()->where('expires_at', '<', now())->with(['sale', 'shop', 'requester'])->lazyById()
                ->each(fn (SaleVoidRequest $request) => $this->voids->expire($request));

            SaleVoidRequest::query()->pending()->whereNull('reminded_at')
                ->where('requested_at', '<=', now()->subHours(self::AFTER_HOURS))
                ->with(['sale', 'shop', 'requester'])->lazyById()
                ->each(function (SaleVoidRequest $request) use (&$reminded): void {
                    $this->inbox->request($this->voids->subject($request), $this->voids->approversOf($request), reminder: true);
                    $request->forceFill(['reminded_at' => now()])->save();
                    $reminded++;
                });
        });

        return $reminded;
    }
}
