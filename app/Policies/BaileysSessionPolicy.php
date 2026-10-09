<?php

namespace App\Policies;

use App\Models\BaileysSession;
use App\Models\User;

class BaileysSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('baileys.view');
    }

    public function view(User $user, BaileysSession $session): bool
    {
        return $user->can('baileys.view');
    }

    public function create(User $user): bool
    {
        return $user->can('baileys.manage');
    }

    public function delete(User $user, BaileysSession $session): bool
    {
        return $user->can('baileys.manage');
    }
}
