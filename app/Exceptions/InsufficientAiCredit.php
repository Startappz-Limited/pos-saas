<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The business's prepaid AI credit cannot cover a call (Phase 4). The work
 * waits for a top-up (AiBlockedWork) and the owner is told.
 */
class InsufficientAiCredit extends RuntimeException
{
    public function __construct(public readonly float $needed, public readonly float $available)
    {
        parent::__construct(__('Not enough AI credit for this request. Top up to continue.'));
    }
}
