<?php

namespace App\Support\Tenancy;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Every change of a person's default business is audited (stage 10a, gaps 1
 * and 2): the switcher, a removal and the cleanup command
 * (`members:clean-removed`) all call this. An unbound flat API token follows
 * the default business, so a change to it must stay traceable even with no
 * signed-in actor — a removal or the cleanup command records "System", as
 * every audit row with no signed-in user does.
 */
final class DefaultBusinessAudit
{
    public static function record(User $user, ?Business $from, ?Business $to, string $reason): void
    {
        if ($from?->id === $to?->id) {
            return;
        }

        $actor = Auth::user();

        AuditableEvent::dispatch(
            auditableType: User::class,
            auditableId: $user->id,
            auditableUuid: $user->uuid,
            event: AuditEvent::UPDATED,
            oldValues: ['default_business_id' => $from?->id],
            newValues: ['default_business_id' => $to?->id, 'reason' => $reason],
            userId: $actor?->id,
            userName: $actor?->name,
            userEmail: $actor?->email,
            tags: ['tenancy', 'default_business_changed'],
            businessId: $to?->id ?? $from?->id,
        );
    }
}
