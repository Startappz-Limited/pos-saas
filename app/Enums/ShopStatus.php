<?php

namespace App\Enums;

enum ShopStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    /**
     * Get the label for the status
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::SUSPENDED => 'Suspended',
        };
    }

    /**
     * Get the color for the status
     */
    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::INACTIVE => 'gray',
            self::SUSPENDED => 'red',
        };
    }

    /**
     * Get the icon for the status
     */
    public function icon(): string
    {
        return match ($this) {
            self::ACTIVE => 'check-circle',
            self::INACTIVE => 'x-circle',
            self::SUSPENDED => 'ban',
        };
    }

    /**
     * Check if the shop can process transactions
     */
    public function canProcessTransactions(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Get all status options for forms
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($status) => [
            $status->value => $status->label(),
        ])->toArray();
    }
}
