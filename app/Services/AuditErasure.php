<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Erases a deleted person's personal data from the audit trail (right to
 * erasure), while keeping the record of what happened.
 *
 * Audit rows are immutable through the AuditLog model on purpose; this is the
 * one sanctioned exception, and it only removes personal data: the actor's
 * name, email, IP address and browser on rows they made, and the personal
 * fields of their own user record in the before/after snapshots.
 */
class AuditErasure
{
    /**
     * Personal fields of a user record, as they appear in audit snapshots.
     */
    private const USER_FIELDS = ['name', 'email', 'phone', 'address', 'date_of_birth', 'profile_photo'];

    /**
     * Like the audit logger, this never fails the deletion it follows: a
     * missing audit store has nothing to erase, and any other failure is
     * reported (not swallowed) so the erasure can be completed by hand.
     */
    public function eraseUser(int $userId): void
    {
        $connection = (new AuditLog)->getConnectionName();

        try {
            if (! Schema::connection($connection)->hasTable('audit_logs')) {
                return;
            }

            $this->erase(DB::connection($connection)->table('audit_logs'), $userId);
        } catch (Throwable $e) {
            report(new RuntimeException("Audit erasure for user #{$userId} failed; erase it by hand.", 0, $e));
        }
    }

    /**
     * @param  Builder  $table
     */
    private function erase($table, int $userId): void
    {
        (clone $table)->where('user_id', $userId)->update([
            'user_name' => self::placeholder($userId),
            'user_email' => null,
            'ip_address' => null,
            'user_agent' => null,
        ]);

        (clone $table)
            ->where('auditable_type', (new User)->getMorphClass())
            ->where('auditable_id', $userId)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    (clone $table)->where('id', $row->id)->update([
                        'old_values' => $this->redact($row->old_values),
                        'new_values' => $this->redact($row->new_values),
                    ]);
                }
            });
    }

    /**
     * How an erased person is named in the audit trail.
     */
    public static function placeholder(int $userId): string
    {
        return "Deleted user #{$userId}";
    }

    private function redact(?string $json): ?string
    {
        if ($json === null) {
            return null;
        }

        $values = json_decode($json, true);

        if (! is_array($values)) {
            return $json;
        }

        foreach (self::USER_FIELDS as $field) {
            if (array_key_exists($field, $values) && $values[$field] !== null) {
                $values[$field] = '[erased]';
            }
        }

        return json_encode($values);
    }
}
