<?php

namespace App\Policies;

use App\Models\BusinessExport;
use App\Models\User;

/**
 * An export holds every customer's and staff member's details: only the
 * business owner downloads it (a super-admin included is refused; see
 * Gate::before).
 */
class BusinessExportPolicy
{
    public function download(User $user, BusinessExport $export): bool
    {
        $ownerId = $export->business?->owner_id;

        return $ownerId !== null && $ownerId === $user->id;
    }
}
