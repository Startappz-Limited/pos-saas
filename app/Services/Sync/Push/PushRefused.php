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
     * @param  array{type: string, key: string, details: array<string, mixed>}|null  $issue  recorded for review even though the item isn't kept
     * @param  array<string, mixed>|null  $refund  a refund owed recorded even though the item isn't kept (stage 10, step 5c)
     */
    public function __construct(public readonly ApiErrorCode $errorCode, string $message, public readonly ?array $errors = null, public readonly ?array $issue = null, public readonly ?array $refund = null)
    {
        parent::__construct($message);
    }
}
