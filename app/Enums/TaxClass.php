<?php

namespace App\Enums;

/**
 * VAT treatment of a product line.
 *
 * The cases mirror the KRA tax categories used on Kenyan tax invoices (and by
 * eTIMS), so a future eTIMS transmission can send `kraCode()` verbatim:
 *
 *   A — Exempt        (e.g. unprocessed foodstuffs, financial services)
 *   B — Standard 16%  (the default for most retail goods)
 *   C — Zero rated 0% (e.g. exports; still a taxable supply, unlike exempt)
 *   D — Non‑VAT       (outside the scope of VAT altogether)
 *   E — Reduced 8%    (retained for petroleum products)
 *
 * Zero‑rated and exempt both charge no VAT but are NOT interchangeable: input
 * VAT is reclaimable on zero‑rated supplies and is not on exempt ones, so the
 * VAT return has to report them separately.
 */
enum TaxClass: string
{
    case Exempt = 'exempt';
    case Standard = 'standard';
    case ZeroRated = 'zero_rated';
    case NonVat = 'non_vat';
    case Reduced = 'reduced';

    public function label(): string
    {
        return match ($this) {
            self::Exempt => 'Exempt',
            self::Standard => 'Standard rated',
            self::ZeroRated => 'Zero rated',
            self::NonVat => 'Non-VAT',
            self::Reduced => 'Reduced rate',
        };
    }

    /**
     * The KRA/eTIMS tax category letter for this class.
     */
    public function kraCode(): string
    {
        return match ($this) {
            self::Exempt => 'A',
            self::Standard => 'B',
            self::ZeroRated => 'C',
            self::NonVat => 'D',
            self::Reduced => 'E',
        };
    }

    /**
     * Whether the supply counts as taxable turnover on the VAT return.
     *
     * Standard, reduced and zero rated supplies are all taxable (zero rated
     * simply at 0%); exempt and non-VAT supplies are not.
     */
    public function isTaxableSupply(): bool
    {
        return match ($this) {
            self::Standard, self::Reduced, self::ZeroRated => true,
            self::Exempt, self::NonVat => false,
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Standard => 'bg-primary-subtle text-primary',
            self::Reduced => 'bg-info-subtle text-info',
            self::ZeroRated => 'bg-warning-subtle text-warning',
            self::Exempt => 'bg-secondary-subtle text-secondary',
            self::NonVat => 'bg-light text-muted',
        };
    }

    /**
     * Options for a <select>, keyed by value.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
