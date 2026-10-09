<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
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
