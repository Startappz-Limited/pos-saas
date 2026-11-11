<?php

namespace App\Enums;

/**
 * What kind of business this is (stage 11, step 5, decided): chosen at
 * sign-up, changeable any time on the business settings page. It restricts
 * nothing — changing it changes only what's created afterwards, never
 * existing products or settings.
 *
 * Each type's row in "Decisions that apply": its stock consumption default
 * (fifo/fefo), whether a new product tracks batches by default, and the unit
 * names offered (never required) on the product form.
 */
enum BusinessType: string
{
    case GeneralRetail = 'general_retail';
    case Supermarket = 'supermarket';
    case Wholesale = 'wholesale';
    case Pharmacy = 'pharmacy';
    case AgroVet = 'agro_vet';
    case Hardware = 'hardware';

    public function label(): string
    {
        return match ($this) {
            self::GeneralRetail => 'General retail',
            self::Supermarket => 'Supermarket',
            self::Wholesale => 'Wholesale',
            self::Pharmacy => 'Pharmacy',
            self::AgroVet => 'Agro-vet',
            self::Hardware => 'Hardware',
        };
    }

    /**
     * The stock consumption order a new business of this type takes
     * (`businesses.settings['stock']['consumption']`): fefo for the two types
     * that sell perishable or dated stock, fifo (the default) for the rest.
     */
    public function consumption(): string
    {
        return match ($this) {
            self::Pharmacy, self::AgroVet => 'fefo',
            default => 'fifo',
        };
    }

    /**
     * Whether a product created under this type tracks batches by default
     * (the product form can still change it either way).
     */
    public function tracksBatchesByDefault(): bool
    {
        return match ($this) {
            self::Pharmacy, self::AgroVet => true,
            default => false,
        };
    }

    /**
     * Unit names offered as suggestions on the product form — never
     * required, never validated against: a free-text field with these as a
     * starting point for the type's typical stock.
     *
     * @return list<string>
     */
    public function unitPresets(): array
    {
        return match ($this) {
            self::GeneralRetail => ['piece', 'pack'],
            self::Supermarket => ['piece', 'kg', 'pack', 'crate'],
            self::Wholesale => ['piece', 'carton', 'crate', 'pallet'],
            self::Pharmacy => ['piece', 'strip', 'box', 'bottle'],
            self::AgroVet => ['piece', 'kg', 'bag', 'bottle'],
            self::Hardware => ['piece', 'box', 'roll', 'meter'],
        };
    }
}
