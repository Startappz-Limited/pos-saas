<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use Auditable;

    /**
     * Platform operator: bypasses every gate (see AppServiceProvider) and is the
     * only role that may see, edit, or grant another super-admin.
     */
    public const SUPER_ADMIN = 'super-admin';

    /**
     * Shop owner: holds every permission but, unlike super-admin, is still
     * evaluated by policies and cannot touch super-admin accounts.
     */
    public const ADMIN = 'admin';

    /**
     * Roles the code refers to by name, so they may not be renamed or deleted.
     *
     * @var array<int, string>
     */
    public const SYSTEM_ROLES = [self::SUPER_ADMIN, self::ADMIN];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'business_id',
        'name',
        'guard_name',
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $role) {
            if (empty($role->uuid)) {
                $role->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key name for Laravel
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The business this role belongs to; null for a global role.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Find or create a global role (business_id NULL), shared by every
     * business. Only the system roles should be global: a global role named
     * like a business role would make lookups by name ambiguous.
     */
    public static function global(string $name, string $guard = 'web'): self
    {
        return self::query()->firstOrCreate(
            ['business_id' => null, 'name' => $name, 'guard_name' => $guard],
        );
    }

    /**
     * Whether this role is shared by every business rather than owned by one.
     */
    public function isGlobal(): bool
    {
        return $this->business_id === null;
    }

    /**
     * Whether the code depends on this role's name.
     */
    public function isSystemRole(): bool
    {
        return $this->isGlobal() && in_array($this->name, self::SYSTEM_ROLES, true);
    }

    /**
     * Roles the user can see: every role for a super-admin; for anyone else
     * the global roles plus their own business's roles.
     *
     * Deliberately a local scope, not a global one: Spatie's permission cache
     * loads roles in the background, and a viewer-dependent global scope would
     * cache one business's view of the roles for everybody.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->isSuperAdmin()) {
            return $query;
        }

        $businessId = $user?->currentBusinessId();

        return $query->where(fn (Builder $q) => $q
            ->whereNull($this->qualifyColumn('business_id'))
            ->when($businessId !== null, fn (Builder $q) => $q->orWhere($this->qualifyColumn('business_id'), $businessId)));
    }

    /**
     * Roles the given user may assign to others. Only a super-admin may grant
     * super-admin; without this, anyone holding users.create could promote
     * themselves past the admin/super-admin split.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeAssignableBy(Builder $query, ?User $user): Builder
    {
        if ($user?->isSuperAdmin()) {
            return $query;
        }

        return $query->visibleTo($user)->where('name', '!=', self::SUPER_ADMIN);
    }
}
