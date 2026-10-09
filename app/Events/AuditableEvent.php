<?php

namespace App\Events;

use App\Enums\AuditEvent;
use App\Enums\AuditStatus;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something auditable happened. Dispatched from the Auditable trait and from
 * services; written to the audit database by the AuditLogger listener.
 *
 * Deliberately carries plain scalars rather than models: the listener must never
 * re-query, and the payload must survive being queued.
 */
class AuditableEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<int, string>|null  $tags
     */
    public function __construct(
        public readonly string $auditableType,
        public readonly ?int $auditableId,
        public readonly ?string $auditableUuid,
        public readonly AuditEvent $event,
        public readonly ?array $oldValues = null,
        public readonly ?array $newValues = null,
        public readonly AuditStatus $status = AuditStatus::SUCCESS,
        public readonly ?int $userId = null,
        public readonly ?string $userName = null,
        public readonly ?string $userEmail = null,
        public readonly ?int $shopId = null,
        public readonly ?array $tags = null,
    ) {}
}
