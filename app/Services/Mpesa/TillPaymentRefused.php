<?php

namespace App\Services\Mpesa;

use App\Enums\ApiErrorCode;
use RuntimeException;

/**
 * An M-Pesa request a till can't make (Phase 7): the shop has no proven
 * M-Pesa number, or the phone isn't one M-Pesa can reach.
 */
class TillPaymentRefused extends RuntimeException
{
    public function __construct(public readonly ApiErrorCode $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public static function notSetUp(): self
    {
        return new self(ApiErrorCode::MpesaNotSetUp, __('This shop has no M-Pesa Till or Paybill set up. The owner adds it in the shop\'s settings.'));
    }

    public static function badPhone(): self
    {
        return new self(ApiErrorCode::ValidationFailed, __('Enter a Kenyan mobile number M-Pesa can reach.'));
    }
}
