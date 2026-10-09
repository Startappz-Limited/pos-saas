<?php

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::SUSPENDED => 'Suspended',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
            self::SUSPENDED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ACTIVE => 'mdi-check-circle',
            self::INACTIVE => 'mdi-minus-circle',
            self::SUSPENDED => 'mdi-cancel',
        };
    }

    public function canLogin(): bool
    {
        return $this === self::ACTIVE;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($status) => [$status->value => $status->label()])
            ->toArray();
    }
}
