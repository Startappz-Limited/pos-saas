<?php

namespace App\Actions\Platform;

use App\Enums\AuditEvent;
use App\Models\Business;
use App\Support\Platform\PlatformAudit;
use DomainException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Reactivates a suspended business (Phase 5, B1): it returns to the status it
 * had (active, or waiting for its owner's email). Audited.
 */
class ReactivateBusiness
{
    public function execute(Business $business): void
    {
        if (! $business->isSuspended()) {
            throw new DomainException(__('This business isn\'t suspended.'));
        }

        $status = $business->status_before_suspension ?: Business::STATUS_ACTIVE;

        $business->forceFill([
            'status' => $status,
            'status_before_suspension' => null,
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
        ])->save();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        PlatformAudit::record('business.reactivated', $business, ['status' => $status], AuditEvent::UPDATED);
    }
}
