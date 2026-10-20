<?php

namespace App\Policies;

use App\Actions\Business\CopyRoleTemplates;
use App\Models\User;

/**
 * People in the current business (stage 3). `users.full-access` counts as
 * every `users.*` permission, but never past owner protection: a non-owner
 * can't change, suspend or remove an owner; only owners remove people; nobody
 * removes themselves; and the business keeps at least one owner.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->holds($user, 'users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $this->holds($user, 'users.view');
    }

    public function create(User $user): bool
    {
        return $this->holds($user, 'users.create');
    }

    public function update(User $user, User $model): bool
    {
        if ($user->is($model)) {
            return $this->holds($user, 'users.update');
        }

        return $this->holds($user, 'users.update') && $this->mayManage($user, $model);
    }

    /**
     * Removing someone from the business (the login itself stays: it may
     * belong to other businesses).
     */
    public function delete(User $user, User $model): bool
    {
        return ! $user->is($model)
            && $user->isBusinessOwner()
            && ! $model->isPrimaryOwner()
            && ! $this->isLastOwner($model);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->isBusinessOwner();
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Owners are managed only by owners; the founding owner only by themselves
     * (or by handing the business over).
     */
    private function mayManage(User $user, User $model): bool
    {
        if ($model->isPrimaryOwner()) {
            return $user->is($model);
        }

        return ! $model->isBusinessOwner() || $user->isBusinessOwner();
    }

    private function isLastOwner(User $model): bool
    {
        return $model->isBusinessOwner()
            && User::query()->inCurrentBusiness()->role(CopyRoleTemplates::OWNER_ROLE)->count() <= 1;
    }

    private function holds(User $user, string $permission): bool
    {
        return $user->can($permission) || $user->can('users.full-access');
    }
}
