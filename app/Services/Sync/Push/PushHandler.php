<?php

namespace App\Services\Sync\Push;

/**
 * Applies one kind of pushed item (Phase 7). Runs inside the item's
 * transaction; throws PushRefused (or a ValidationException) to refuse it.
 */
interface PushHandler
{
    /**
     * @return array<string, mixed> what the till needs back (ids, numbers, flags)
     */
    public function handle(PushItem $item): array;
}
