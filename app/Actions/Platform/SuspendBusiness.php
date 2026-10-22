<?php

namespace App\Actions\Platform;

use App\Enums\AuditEvent;
use App\Models\Business;
use App\Models\User;
use App\Support\Platform\PlatformAudit;
use DomainException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Suspends a business from the platform console (Phase 5, B1). Nobody works
 * in it any more: tenant resolution chooses only active businesses, so its
 * members' sessions and both kinds of API token stop reaching it on their
 * next request, and its background work stops. Reactivating returns it to
 * the status it had. Audited.
 */
class SuspendBusiness
{
    public function execute(Business $business, User $operator, string $reason): void
    {
        if (! in_array($business->status, [Business::STATUS_ACTIVE, Business::STATUS_PENDING_VERIFICATION], true)) {
            throw new DomainException(__('Only an active business, or one waiting for its owner\'s email, can be suspended.'));
        }

        $before = $business->status;

        $business->forceFill([
            'status' => Business::STATUS_SUSPENDED,
            'status_before_suspension' => $before,
            'suspended_at' => now(),
            'suspended_by' => $operator->id,
            'suspension_reason' => $reason,
        ])->save();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        PlatformAudit::record('business.suspended', $business, ['status_before' => $before, 'reason' => $reason], AuditEvent::UPDATED);
    }
}
