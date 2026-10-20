<?php

namespace App\Services\Mpesa;

use App\Contracts\MpesaGateway;

/**
 * Bound until the private M-Pesa package is connected: it proves nothing, so
 * every check fails and no shortcode is accepted.
 */
class NullMpesaGateway implements MpesaGateway
{
    public function verify(string $shortcode, string $type, array $credentials): MpesaCheck
    {
        return MpesaCheck::failed(__('The M-Pesa connection is not set up on this system yet.'));
    }
}
