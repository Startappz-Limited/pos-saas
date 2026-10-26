<?php

namespace App\Services\Mpesa;

use App\Contracts\MpesaGateway;

/**
 * Bound until the private M-Pesa package is connected: it proves nothing, so
 * every check fails, no shortcode is accepted, no STK Push is sent and no
 * payment is ever confirmed.
 */
class NullMpesaGateway implements MpesaGateway
{
    public function verify(string $shortcode, string $type, array $credentials): MpesaCheck
    {
        return MpesaCheck::failed(__('The M-Pesa connection is not set up on this system yet.'));
    }

    public function stkPush(MpesaAccount $account, string $phone, string $amount, string $reference, string $description): StkRequest
    {
        return StkRequest::refused(__('The M-Pesa connection is not set up on this system yet.'));
    }

    public function stkStatus(MpesaAccount $account, string $checkoutRequestId): MpesaPayment
    {
        return MpesaPayment::pending();
    }

    public function findTransaction(MpesaAccount $account, string $receipt): ?MpesaPayment
    {
        return null;
    }
}
