<?php

namespace App\Support\Tenancy;

use App\Models\Business;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The business the current request, job or command acts for (decision 4.3).
 *
 * Set by ResolveTenant for signed-in requests (the session's business on the
 * web, the user's default business for API tokens), and explicitly by code
 * that runs without a signed-in user: webhooks, jobs, commands, seeders.
 * Bound as a scoped singleton, so it never outlives a request or a job.
 */
class TenantContext
{
    /** The hidden log-context key queued work carries its business under. */
    public const CONTEXT_KEY = 'tenant.business_id';

    private ?Business $business = null;

    private int $bypassDepth = 0;

    public function set(Business|int|null $business): void
    {
        $this->business = is_int($business) ? Business::findOrFail($business) : $business;

        // Roles and permissions are held per business (Spatie teams). Relations
        // already loaded for another business must not answer for this one.
        setPermissionsTeamId($this->business?->id);

        if (($user = Auth::user()) instanceof User) {
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    public function forget(): void
    {
        $this->set(null);
    }

    public function business(): ?Business
    {
        return $this->business;
    }

    public function id(): ?int
    {
        return $this->business?->id;
    }

    public function has(): bool
    {
        return $this->business !== null;
    }

    /**
     * Runs $callback for $business, then restores the previous business.
     *
     * Jobs queued inside carry $business. Don't return a pending dispatch
     * (`fn () => Job::dispatch()`): it is queued when released, after the
     * previous business is back.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Business|int $business, Closure $callback): mixed
    {
        $previous = $this->business;
        $this->set($business);

        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }

    /**
     * Runs $callback with no business set, then restores the previous one:
     * for platform-wide records, such as the seeded role templates.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withoutBusiness(Closure $callback): mixed
    {
        $previous = $this->business;
        $this->set(null);

        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }

    /**
     * Runs $callback with tenant scoping switched off: for platform code that
     * deliberately works across businesses, such as resolving which business a
     * webhook belongs to. Every caller is listed in an architecture test.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function bypass(Closure $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }

    public function bypassing(): bool
    {
        return $this->bypassDepth > 0;
    }

    /**
     * Whether a tenant scope should filter by the current business. With no
     * business set: in strict mode (local, testing) the query is refused; a
     * signed-in user gets no rows; code with no signed-in user is refused too,
     * since jobs, commands and the scheduler all act for a business (stage
     * 1c) and running unscoped would read every business.
     */
    public function guardMissing(Builder $builder, Model $model): bool
    {
        if ($this->has()) {
            return true;
        }

        if (config('tenancy.strict') || ! Auth::hasUser()) {
            throw MissingTenantContext::forModel(class_basename($model));
        }

        $builder->whereRaw('1 = 0');

        return false;
    }

    /**
     * Runs $callback once for each business that takes background work, acting
     * for it (stage 1c): scheduler sweeps. A failure in one business is
     * reported and the others still run.
     *
     * @param  Closure(Business): mixed  $callback
     */
    public function eachBusiness(Closure $callback): void
    {
        foreach (Business::query()->orderBy('id')->cursor() as $business) {
            if (! $business->runsBackgroundWork()) {
                continue;
            }

            try {
                $this->run($business, fn () => $callback($business));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Limits a query-builder query (DB::table, which no model scope reaches)
     * to the shops the viewer may access in the current business, by its
     * shop column (stage 1c). With no business set it behaves as the model
     * scopes do: refused in strict mode, no rows for a signed-in user.
     */
    public function restrictToShops(QueryBuilder $query, string $column): QueryBuilder
    {
        if ($this->bypassing() || ! $this->guardMissingQuery($query, $column)) {
            return $query;
        }

        return $query->whereIn($column, $this->accessibleShopsQuery($this->viewer()));
    }

    /**
     * Limits a query-builder query to the current business, by its business
     * column (stage 1c), for business-wide rows with no shop.
     */
    public function restrictToBusiness(QueryBuilder $query, string $column): QueryBuilder
    {
        if ($this->bypassing() || ! $this->guardMissingQuery($query, $column)) {
            return $query;
        }

        return $query->where($column, $this->id());
    }

    /**
     * guardMissing() for a query-builder query.
     */
    private function guardMissingQuery(QueryBuilder $query, string $column): bool
    {
        if ($this->has()) {
            return true;
        }

        if (config('tenancy.strict') || ! Auth::hasUser()) {
            throw MissingTenantContext::forModel($column);
        }

        $query->whereRaw('1 = 0');

        return false;
    }

    /**
     * The current business's shops $user may access (decision 4.4), as a
     * subquery of shop ids: every shop for an owner or for code with no
     * signed-in user; otherwise only the shops assigned to the user, so no
     * assignment means no shop (default-deny). Built fresh for each query, so
     * shops and assignments made earlier in the same request count.
     */
    public function accessibleShopsQuery(?User $user): QueryBuilder
    {
        $query = DB::table('shops')->select('shops.id')->where('shops.business_id', $this->id());

        if ($user === null || $user->isBusinessOwner()) {
            return $query;
        }

        return $query->whereIn('shops.id', DB::table('shop_user')->select('shop_user.shop_id')->where('shop_user.user_id', $user->id));
    }

    /**
     * @return list<int>
     */
    public function accessibleShopIdsFor(?User $user): array
    {
        return $this->accessibleShopsQuery($user)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * The signed-in user whose shop access the scopes apply; null for code
     * with no signed-in user.
     */
    public function viewer(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
