<?php

namespace App\Models;

use App\Actions\Business\CopyRoleTemplates;
use App\Models\Concerns\Auditable;
use App\Models\Scopes\BusinessOrSharedScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * The people holding this role in the current business. Spatie's
     * users() spans every business (a role is held per business, stage 3),
     * so counts and the "still held" check use this instead.
     */
    public function holders(): BelongsToMany
    {
        return $this->users()->wherePivot(app(PermissionRegistrar::class)->teamsKey, app(TenantContext::class)->id());
    }

    /**
     * The shared template a business's copy was made from (Phase 5). The
     * role scope hides a template behind the business's copy of it, so the
     * link reads past that scope.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(self::class, 'template_id')->withoutGlobalScope(BusinessOrSharedScope::class);
    }

    /**
     * A business's copy of a shared template: can't be deleted or renamed,
     * can be reset to the template's permissions.
     */
    public function isTemplateCopy(): bool
    {
        return $this->template_id !== null;
    }

    /**
     * The owner role (decision 4.4): never changed from a business.
     */
    public function isOwnerRole(): bool
    {
        return $this->name === CopyRoleTemplates::OWNER_ROLE;
    }

    /**
     * A shared template (no business): changed only by the platform.
     */
    public function isShared(): bool
    {
        return $this->business_id === null;
    }

    /**
     * Whether the role belongs to the current business.
     */
    public function belongsToCurrentBusiness(): bool
    {
        $businessId = app(TenantContext::class)->id();

        return $businessId !== null && (int) $this->business_id === $businessId;
    }

    /**
     * Get the route key name for Laravel
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
