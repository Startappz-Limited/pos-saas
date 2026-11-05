<?php

namespace App\Services\Sync\Push;

use App\Enums\ApiErrorCode;
use RuntimeException;

/**
 * A pushed item that needs another item first (stage 10, step 5c): a void of a
 * sale the server hasn't received yet. Not refused: the result is `pending`
 * ("not yet applied"), nothing is kept and no receipt is written, so the till
 * sends it again with its next push.
 */
class PushPending extends RuntimeException
{
    public function __construct(public readonly ApiErrorCode $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
