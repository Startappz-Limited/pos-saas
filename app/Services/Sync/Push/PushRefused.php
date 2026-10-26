<?php

namespace App\Services\Sync\Push;

use App\Enums\ApiErrorCode;
use RuntimeException;

/**
 * A pushed item the server won't apply (Phase 7): it stays on the till, which
 * shows the reason. Nothing of it is kept, so it can be pushed again once the
 * reason is gone.
 */
class PushRefused extends RuntimeException
{
    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public function __construct(public readonly ApiErrorCode $errorCode, string $message, public readonly ?array $errors = null)
    {
        parent::__construct($message);
    }
}
