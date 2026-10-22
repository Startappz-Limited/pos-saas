<?php

namespace App\Support\Platform;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;

/**
 * The audit trail of the platform console (Phase 5, B5): every console
 * action, cross-business reads included, written to the audit log with the
 * operator, the business it concerned and the action. A row about a business
 * carries that business, so its owner sees what the platform did in it.
 */
final class PlatformAudit
{
    /**
     * @param  string  $action  e.g. "businesses.viewed", "business.suspended"
     * @param  array<string, mixed>  $context  what the action concerned or changed
     */
    public static function record(string $action, ?Business $business = null, array $context = [], AuditEvent $event = AuditEvent::VIEWED): void
    {
        $operator = Auth::user();

        AuditableEvent::dispatch(
            // audit_logs.auditable_id can't be null: an action concerning no
            // business is recorded against "platform", id 0.
            auditableType: $business !== null ? Business::class : 'platform',
            auditableId: $business?->id ?? 0,
            auditableUuid: $business?->uuid,
            event: $event,
            newValues: ['action' => $action] + $context,
            userId: $operator?->id,
            userName: $operator?->name,
            userEmail: $operator?->email,
            tags: ['platform', $action],
            businessId: $business?->id,
        );
    }
}
