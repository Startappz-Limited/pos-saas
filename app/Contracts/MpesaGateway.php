<?php

namespace App\Contracts;

use App\Services\Mpesa\MpesaCheck;

/**
 * The boundary to the private M-Pesa package (decided 12 October 2026, stage
 * 3): the package acts as an API, each shop supplies its own account details,
 * and a payment is confirmed by querying that API.
 *
 * Until the package is plugged in, NullMpesaGateway is bound and every check
 * fails, so nothing is accepted unproven. Tests bind FakeMpesaGateway.
 */
interface MpesaGateway
{
    /**
     * Proves the account controls $shortcode with a test query.
     *
     * @param  'till'|'paybill'  $type
     * @param  array{consumer_key: string, consumer_secret: string, passkey?: string|null}  $credentials
     */
    public function verify(string $shortcode, string $type, array $credentials): MpesaCheck;
}
