<?php

namespace App\Enums;

enum EcommerceOrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case OnHold = 'on-hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::OnHold => 'On Hold',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Processing => 'info',
            self::OnHold => 'secondary',
            self::Completed => 'success',
            self::Cancelled => 'danger',
            self::Refunded => 'dark',
            self::Failed => 'danger',
        };
    }

    public function canBeConverted(): bool
    {
        return in_array($this, [self::Pending, self::Processing, self::OnHold, self::Completed]);
    }

    public function canChangeStatus(): bool
    {
        return ! in_array($this, [self::Cancelled, self::Refunded, self::Failed]);
    }

    /**
     * Get the status transitions allowed from the current status.
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::OnHold, self::Cancelled],
            self::Processing => [self::Completed, self::OnHold, self::Cancelled],
            self::OnHold => [self::Processing, self::Pending, self::Cancelled],
            self::Completed => [self::Refunded],
            default => [],
        };
    }

    /**
     * Icon for each status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'solar:clock-circle-bold-duotone',
            self::Processing => 'solar:refresh-bold-duotone',
            self::OnHold => 'solar:pause-circle-bold-duotone',
            self::Completed => 'solar:check-circle-bold-duotone',
            self::Cancelled => 'solar:close-circle-bold-duotone',
            self::Refunded => 'solar:undo-left-round-bold-duotone',
            self::Failed => 'solar:danger-triangle-bold-duotone',
        };
    }

    /**
     * Button style class for each status.
     */
    public function buttonClass(): string
    {
        return match ($this) {
            self::Pending => 'btn-soft-warning',
            self::Processing => 'btn-soft-info',
            self::OnHold => 'btn-soft-secondary',
            self::Completed => 'btn-soft-success',
            self::Cancelled => 'btn-soft-danger',
            self::Refunded => 'btn-soft-dark',
            self::Failed => 'btn-soft-danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn(self $status) => [$status->value => $status->label()])->all();
    }
}
