<?php

namespace App\Enums;

enum ExpenseCategoryType: string
{
    case OPERATIONAL = 'operational';
    case ADMINISTRATIVE = 'administrative';
    case MARKETING = 'marketing';
    case PAYROLL = 'payroll';
    case UTILITIES = 'utilities';
    case RENT = 'rent';
    case SUPPLIES = 'supplies';
    case MAINTENANCE = 'maintenance';
    case TRANSPORT = 'transport';
    case INSURANCE = 'insurance';
    case TAXES = 'taxes';
    case MISCELLANEOUS = 'miscellaneous';

    public function label(): string
    {
        return match ($this) {
            self::OPERATIONAL => 'Operational',
            self::ADMINISTRATIVE => 'Administrative',
            self::MARKETING => 'Marketing & Advertising',
            self::PAYROLL => 'Payroll & Benefits',
            self::UTILITIES => 'Utilities',
            self::RENT => 'Rent & Lease',
            self::SUPPLIES => 'Supplies',
            self::MAINTENANCE => 'Maintenance & Repairs',
            self::TRANSPORT => 'Transport & Logistics',
            self::INSURANCE => 'Insurance',
            self::TAXES => 'Taxes & Licenses',
            self::MISCELLANEOUS => 'Miscellaneous',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::OPERATIONAL => 'ri-settings-3-line',
            self::ADMINISTRATIVE => 'ri-briefcase-line',
            self::MARKETING => 'ri-megaphone-line',
            self::PAYROLL => 'ri-team-line',
            self::UTILITIES => 'ri-lightbulb-line',
            self::RENT => 'ri-building-line',
            self::SUPPLIES => 'ri-archive-line',
            self::MAINTENANCE => 'ri-tools-line',
            self::TRANSPORT => 'ri-truck-line',
            self::INSURANCE => 'ri-shield-check-line',
            self::TAXES => 'ri-government-line',
            self::MISCELLANEOUS => 'ri-more-2-line',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OPERATIONAL => 'primary',
            self::ADMINISTRATIVE => 'secondary',
            self::MARKETING => 'info',
            self::PAYROLL => 'warning',
            self::UTILITIES => 'success',
            self::RENT => 'dark',
            self::SUPPLIES => 'light',
            self::MAINTENANCE => 'danger',
            self::TRANSPORT => 'primary',
            self::INSURANCE => 'secondary',
            self::TAXES => 'warning',
            self::MISCELLANEOUS => 'dark',
        };
    }

    public function isTaxDeductible(): bool
    {
        return in_array($this, [
            self::OPERATIONAL,
            self::PAYROLL,
            self::UTILITIES,
            self::RENT,
            self::SUPPLIES,
            self::MAINTENANCE,
            self::TRANSPORT,
            self::INSURANCE,
        ]);
    }
}
