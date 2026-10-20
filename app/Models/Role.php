<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Scopes\BusinessOrSharedScope;
use Illuminate\Support\Str;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class Role extends SpatieRole
{
    use Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_id',
        'uuid',
        'name',
        'guard_name',
    ];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        // Shared role templates (business_id NULL) plus the current
        // business's own roles (Spatie teams, stage 1a).
        static::addGlobalScope(new BusinessOrSharedScope);

        static::creating(function (self $role) {
            if (empty($role->uuid)) {
                $role->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Spatie's lookup by name, preferring the current business's own role to
     * a shared template of the same name (stage 3: each business has its own
     * copies of the templates).
     */
    protected static function findByParam(array $params = []): ?RoleContract
    {
        $query = static::query();
        $registrar = app(PermissionRegistrar::class);

        if ($registrar->teams) {
            $teamsKey = $registrar->teamsKey;
            $query->where(fn ($q) => $q->whereNull($teamsKey)->orWhere($teamsKey, $params[$teamsKey] ?? getPermissionsTeamId()))
                ->orderByRaw("{$teamsKey} is null");
            unset($params[$teamsKey]);
        }

        foreach ($params as $key => $value) {
            $query->where($key, $value);
        }

        return $query->first();
    }

    /**
     * Get the route key name for Laravel
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
