<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Scopes\BusinessOrSharedScope;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

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
     * Get the route key name for Laravel
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
