<?php

namespace App\Services\Sync\Push;

use App\Models\Register;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * One item of a till's push (Phase 7): what it is, when the till recorded it,
 * and the till and person pushing it.
 */
final class PushItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $clientUuid,
        public readonly string $type,
        public readonly CarbonInterface $recordedAt,
        public readonly array $data,
        public readonly Register $register,
        public readonly User $user,
        // A held item accepted on the till sync review (stage 10, step 5c): which checks the
        // manager's acceptance stands in for (`skip_lease`, `invoice_as_given`, `as_recorded`).
        public readonly array $accept = [],
    ) {}

    public function accepting(string $option): bool
    {
        return (bool) ($this->accept[$option] ?? false);
    }
}
