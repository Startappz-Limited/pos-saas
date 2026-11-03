<?php

namespace App\Support\Approvals;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who may settle an approval request (decided 3 November 2026, stage 10): the
 * members of the current business, active in it, who hold the approve
 * permission there and may work in the request's shop. Never a role name: the
 * owner gives the permission to whoever they choose.
 *
 * Nobody approves their own request, except an owner when no other member is
 * eligible; they then approve with a required note, flagged "self-approved".
 */
final class Approvers
{
    /**
     * @param  list<string>  $permissions  any one of them makes a person eligible
     * @return Collection<int, User>
     */
    public function eligible(array $permissions, int $shopId, ?User $except = null): Collection
    {
        return User::query()
            ->whereBusinessStatus(UserStatus::ACTIVE)
            ->where('status', UserStatus::ACTIVE)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->id))
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user): bool => $this->holds($user, $permissions) && $user->canAccessShop($shopId))
            ->values();
    }

    /**
     * Whether $user may approve a request they made: an owner holding the
     * permission, with nobody else eligible.
     *
     * @param  list<string>  $permissions
     */
    public function mayApproveOwn(User $user, array $permissions, int $shopId): bool
    {
        return $user->isBusinessOwner()
            && $this->holds($user, $permissions)
            && $this->eligible($permissions, $shopId, $user)->isEmpty();
    }

    /**
     * @param  list<string>  $permissions
     */
    public function holds(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
