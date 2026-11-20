<?php

namespace App\Enums;

/**
 * Why a stock layer is in quarantine (stage 11, quarantine policy). Quarantined stock is never sold; it leaves
 * quarantine only through an approved release, or through a disposal or supplier return.
 */
enum QuarantineStatus: string
{
    // The batch or expiry of returned stock could not be established: it must be verified before it can be released.
    case UNVERIFIED = 'unverified';

    // Known to be expired. A medical product's expired stock cannot be released for sale.
    case EXPIRED = 'expired';

    // Batch and expiry known and not yet expired, held until an approved release.
    case HELD = 'held';

    public function label(): string
    {
        return match ($this) {
            self::UNVERIFIED => 'Batch or expiry unverified',
            self::EXPIRED => 'Expired',
            self::HELD => 'Held for release',
        };
    }
}
