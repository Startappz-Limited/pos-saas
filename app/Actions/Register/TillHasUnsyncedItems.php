<?php

namespace App\Actions\Register;

use Carbon\CarbonInterface;
use RuntimeException;

/**
 * Re-pairing a till whose device last reported unsynced items, without
 * confirming (stage 10, step 5c): answered 422 `till_has_unsynced_items`.
 */
class TillHasUnsyncedItems extends RuntimeException
{
    public function __construct(public readonly int $count, public readonly ?CarbonInterface $reportedAt)
    {
        parent::__construct(__('This till last reported :count unsynced item(s). Pairing a new device loses them. Confirm to pair anyway.', ['count' => $count]));
    }
}
