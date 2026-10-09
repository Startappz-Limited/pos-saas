<?php

namespace App\Enums;

enum AuditEvent: string
{
    // Model events
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case RESTORED = 'restored';
    case FORCE_DELETED = 'force_deleted';

    // Authentication events
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case FAILED_LOGIN = 'failed_login';
    case PASSWORD_RESET = 'password_reset';
    case PASSWORD_CHANGED = 'password_changed';
    case TWO_FACTOR_ENABLED = 'two_factor_enabled';
    case TWO_FACTOR_DISABLED = 'two_factor_disabled';

    // Access events
    case VIEWED = 'viewed';
    case EXPORTED = 'exported';
    case PRINTED = 'printed';

    // Status changes
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SUBMITTED = 'submitted';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::UPDATED => 'Updated',
            self::DELETED => 'Deleted',
            self::RESTORED => 'Restored',
            self::FORCE_DELETED => 'Permanently Deleted',
            self::LOGIN => 'Logged In',
            self::LOGOUT => 'Logged Out',
            self::FAILED_LOGIN => 'Failed Login',
            self::PASSWORD_RESET => 'Password Reset',
            self::PASSWORD_CHANGED => 'Password Changed',
            self::TWO_FACTOR_ENABLED => '2FA Enabled',
            self::TWO_FACTOR_DISABLED => '2FA Disabled',
            self::VIEWED => 'Viewed',
            self::EXPORTED => 'Exported',
            self::PRINTED => 'Printed',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::SUBMITTED => 'Submitted',
            self::CANCELLED => 'Cancelled',
            self::COMPLETED => 'Completed',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CREATED => 'mdi-plus-circle',
            self::UPDATED => 'mdi-pencil',
            self::DELETED => 'mdi-delete',
            self::RESTORED => 'mdi-restore',
            self::FORCE_DELETED => 'mdi-delete-forever',
            self::LOGIN => 'mdi-login',
            self::LOGOUT => 'mdi-logout',
            self::FAILED_LOGIN => 'mdi-login-variant',
            self::PASSWORD_RESET, self::PASSWORD_CHANGED => 'mdi-key',
            self::TWO_FACTOR_ENABLED, self::TWO_FACTOR_DISABLED => 'mdi-shield',
            self::VIEWED => 'mdi-eye',
            self::EXPORTED => 'mdi-download',
            self::PRINTED => 'mdi-printer',
            self::APPROVED => 'mdi-check-circle',
            self::REJECTED => 'mdi-close-circle',
            self::SUBMITTED => 'mdi-send',
            self::CANCELLED => 'mdi-cancel',
            self::COMPLETED => 'mdi-check-all',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CREATED => 'success',
            self::UPDATED => 'info',
            self::DELETED, self::FORCE_DELETED => 'danger',
            self::RESTORED => 'warning',
            self::LOGIN, self::APPROVED, self::COMPLETED => 'success',
            self::LOGOUT => 'secondary',
            self::FAILED_LOGIN, self::REJECTED => 'danger',
            self::PASSWORD_RESET, self::PASSWORD_CHANGED => 'warning',
            default => 'primary',
        };
    }

    public function isModelEvent(): bool
    {
        return in_array($this, [
            self::CREATED,
            self::UPDATED,
            self::DELETED,
            self::RESTORED,
            self::FORCE_DELETED,
        ]);
    }

    public function isAuthEvent(): bool
    {
        return in_array($this, [
            self::LOGIN,
            self::LOGOUT,
            self::FAILED_LOGIN,
            self::PASSWORD_RESET,
            self::PASSWORD_CHANGED,
            self::TWO_FACTOR_ENABLED,
            self::TWO_FACTOR_DISABLED,
        ]);
    }
}
