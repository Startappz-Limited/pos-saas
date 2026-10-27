<?php

namespace App\Contracts;

use App\Services\Mpesa\MpesaAccount;
use App\Services\Mpesa\MpesaCheck;
use App\Services\Mpesa\MpesaPayment;
use App\Services\Mpesa\StkRequest;

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

    /**
     * Asks the API to send an STK Push from $account to $phone (Phase 7, till
     * payments). $reference is the payment request's own id, which the
     * API echoes back.
     */
    public function stkPush(MpesaAccount $account, string $phone, string $amount, string $reference, string $description): StkRequest;

    /**
     * Asks the API what became of an STK Push: paid (with its receipt),
     * still pending, or failed or cancelled.
     */
    public function stkStatus(MpesaAccount $account, string $checkoutRequestId): MpesaPayment;

    /**
     * Looks up a payment made to $account by its M-Pesa receipt (a code the
     * customer received and a cashier typed in on an offline till); null
     * when the API knows no such payment to this account.
     */
    public function findTransaction(MpesaAccount $account, string $receipt): ?MpesaPayment;
}
