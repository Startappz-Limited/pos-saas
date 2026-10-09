<?php

namespace App\Listeners;

use App\Events\AuditableEvent;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes audit records to the isolated `audit` connection.
 *
 * Never throws. A shop must be able to complete a sale even if the audit sink is
 * unreachable, so a failure here is logged and swallowed — see
 * .claude/skills/laravel-audit/SKILL.md.
 */
class AuditLogger
{
    /**
     * Keys stripped outright — these must never reach the audit database.
     */
    private const SECRET_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'api_token',
        'api_secret',
        'access_token',
        'refresh_token',
        'secret',
        'consumer_key',
        'consumer_secret',
        'webhook_secret',
        'credentials',
        'settings',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Keys masked to a last-4 form — useful for correlation, not for exposure.
     */
    private const PII_KEYS = [
        'card_last_four',
        'mobile_number',
        'national_id',
        'tax_id',
        'ssn',
    ];

    public function handle(AuditableEvent $event): void
    {
        try {
            AuditLog::create([
                'user_id' => $event->userId,
                'user_name' => $event->userName,
                'user_email' => $event->userEmail,
                'auditable_type' => $event->auditableType,
                'auditable_id' => $event->auditableId,
                'auditable_uuid' => $event->auditableUuid,
                'event' => $event->event->value,
                'status' => $event->status->value,
                'old_values' => $this->sanitize($event->oldValues),
                'new_values' => $this->sanitize($event->newValues),
                'ip_address' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 480, ''),
                'url' => Str::limit((string) request()->fullUrl(), 480, ''),
                'method' => request()->method(),
                'tags' => $event->tags,
                'shop_id' => $event->shopId,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Deliberately swallowed: audit failure must not break business flow.
            Log::critical('Audit logging failed', [
                'auditable_type' => $event->auditableType,
                'auditable_uuid' => $event->auditableUuid,
                'event' => $event->event->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null || $values === []) {
            return null;
        }

        $clean = [];

        foreach ($values as $key => $value) {
            $lower = strtolower((string) $key);

            if (in_array($lower, self::SECRET_KEYS, true)) {
                continue;
            }

            if (in_array($lower, self::PII_KEYS, true) && is_scalar($value) && $value !== null) {
                $clean[$key] = $this->mask((string) $value);

                continue;
            }

            // Nested arrays can hide secrets, so recurse rather than trust them.
            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean === [] ? null : $clean;
    }

    private function mask(string $value): string
    {
        return strlen($value) <= 4 ? '****' : '****'.substr($value, -4);
    }
}
