<?php

namespace App\Support\Business;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;

/**
 * Every change of a business's default shop is audited (stage 10a, step 3):
 * the business settings page and `businesses:set-default-shop`'s backfill.
 * With no signed-in user (the backfill command), the row's actor is empty,
 * shown everywhere as "System".
 */
final class DefaultShopAudit
{
    public static function record(Business $business, ?int $from, ?int $to): void
    {
        if ($from === $to) {
            return;
        }

        $actor = Auth::user();

        AuditableEvent::dispatch(
            auditableType: Business::class,
            auditableId: $business->id,
            auditableUuid: (string) $business->uuid,
            event: AuditEvent::UPDATED,
            oldValues: ['default_shop_id' => $from],
            newValues: ['default_shop_id' => $to],
            userId: $actor?->id,
            userName: $actor?->name,
            userEmail: $actor?->email,
            tags: ['settings', 'default_shop_id'],
            businessId: $business->id,
        );
    }
}
