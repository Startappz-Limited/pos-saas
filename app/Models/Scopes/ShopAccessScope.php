<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Limits a shop-owned model to the shops the signed-in user may access: every
 * shop of their business for an admin, their assigned shops for staff.
 *
 * Applies only when a user is already resolved on the current guard. Queued
 * jobs, webhooks, signed document links and console commands have no user and
 * stay unscoped, exactly as before. A super-admin is never scoped.
 */
class ShopAccessScope implements Scope
{
    public function __construct(private readonly string $column = 'shop_id') {}

    public function apply(Builder $builder, Model $model): void
    {
        $user = self::currentUser();

        if ($user === null || $user->isSuperAdmin()) {
            return;
        }

        $builder->whereIn($model->qualifyColumn($this->column), $user->accessibleShopIds()->all());
    }

    /**
     * The signed-in user, without triggering authentication.
     *
     * hasUser() rather than user(): resolving the user from the session queries
     * the users table, and a scope that did that could recurse.
     */
    public static function currentUser(): ?User
    {
        $guard = Auth::guard();

        if (! $guard->hasUser()) {
            return null;
        }

        $user = $guard->user();

        return $user instanceof User ? $user : null;
    }
}
