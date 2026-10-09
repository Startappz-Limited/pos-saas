<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use App\Models\Scopes\ShopAccessScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Auditable;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use HasRoles {
        assignRole as private spatieAssignRole;
        removeRole as private spatieRemoveRole;
        syncRoles as private spatieSyncRoles;
        givePermissionTo as private spatieGivePermissionTo;
        syncPermissions as private spatieSyncPermissions;
        revokePermissionTo as private spatieRevokePermissionTo;
    }

    /**
     * Request-attribute key prefix for memoised accessible shop IDs.
     */
    private const SHOP_ACCESS_CACHE_PREFIX = 'shop-access.';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'password',
        'phone',
        'address',
        'date_of_birth',
        'profile_photo',
        'status',
        'business_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Matches the column default, so a user created without a status is
     * active before it is ever reloaded from the database (registration does
     * this, and EnsureUserIsActive checks the in-memory model).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
            if (auth()->check() && empty($user->created_by)) {
                $user->created_by = auth()->id();
            }
        });

        static::updating(function (self $user) {
            if (auth()->check()) {
                $user->updated_by = auth()->id();
            }
        });
    }

    protected static function booted(): void
    {
        // Deactivating or suspending someone signs them out everywhere at once,
        // whichever screen or API made the change
        static::updated(function (self $user): void {
            if ($user->wasChanged('status') && ! $user->isActive()) {
                $user->signOutEverywhere();
            }
        });
    }

    /**
     * End every web session and revoke every app token of this user.
     */
    public function signOutEverywhere(): void
    {
        $this->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $this->getKey())->delete();
        }
    }

    /**
     * Get the route key name for Laravel
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    /**
     * Whether the user is a platform super-admin (not a shop owner).
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * Check if user is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === UserStatus::SUSPENDED;
    }

    /**
     * Activate user
     */
    public function activate(): bool
    {
        return $this->update(['status' => UserStatus::ACTIVE]);
    }

    /**
     * Deactivate user
     */
    public function deactivate(): bool
    {
        return $this->update(['status' => UserStatus::INACTIVE]);
    }

    /**
     * Suspend user
     */
    public function suspend(): bool
    {
        return $this->update(['status' => UserStatus::SUSPENDED]);
    }

    /**
     * Scope: Active users
     */
    public function scopeActive($query)
    {
        return $query->where('status', UserStatus::ACTIVE);
    }

    /**
     * Scope: Inactive users
     */
    public function scopeInactive($query)
    {
        return $query->where('status', UserStatus::INACTIVE);
    }

    /**
     * Scope: Suspended users
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', UserStatus::SUSPENDED);
    }

    /**
     * Get user's full name with email
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->name} ({$this->email})";
    }

    /**
     * Get profile photo URL
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo) {
            return asset('storage/'.$this->profile_photo);
        }

        // Default avatar using UI Avatars
        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&color=7F9CF5&background=EBF4FF';
    }

    /**
     * Relationship: Shops the user belongs to
     */
    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_user')
            ->withTimestamps();
    }

    /**
     * The business this user works in (null for a platform super-admin).
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The user's roles, wherever they are read from.
     *
     * Spatie's teams mode filters this relation by one request-wide "current
     * team", which is wrong whenever a user other than the signed-in one is
     * checked (a target user's roles, a user list from several businesses).
     * A user's role assignments only ever live in their own business, so the
     * relation needs no team filter at all.
     */
    public function roles(): BelongsToMany
    {
        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            app(PermissionRegistrar::class)->pivotRole
        );
    }

    /**
     * The user's direct permissions; unfiltered for the same reason as roles().
     */
    public function permissions(): BelongsToMany
    {
        return $this->morphToMany(
            config('permission.models.permission'),
            'model',
            config('permission.table_names.model_has_permissions'),
            config('permission.column_names.model_morph_key'),
            app(PermissionRegistrar::class)->pivotPermission
        );
    }

    /**
     * Assign roles, resolving names within the user's own business.
     */
    public function assignRole(...$roles): static
    {
        return $this->inOwnBusiness(fn () => $this->spatieAssignRole(...$roles));
    }

    public function removeRole(...$role): static
    {
        return $this->inOwnBusiness(fn () => $this->spatieRemoveRole(...$role));
    }

    public function syncRoles(...$roles): static
    {
        return $this->inOwnBusiness(fn () => $this->spatieSyncRoles(...$roles));
    }

    public function givePermissionTo(...$permissions): static
    {
        return $this->inOwnBusiness(fn () => $this->spatieGivePermissionTo(...$permissions));
    }

    public function syncPermissions(...$permissions): static
    {
        return $this->inOwnBusiness(fn () => $this->spatieSyncPermissions(...$permissions));
    }

    public function revokePermissionTo($permission): static
    {
        return $this->inOwnBusiness(fn () => $this->spatieRevokePermissionTo($permission));
    }

    /**
     * Run a Spatie write with the "current team" set to this user's business,
     * so role names resolve to that business's roles (plus the global ones)
     * and the assignment is recorded against it. 0 means "no business".
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inOwnBusiness(callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($this->business_id ?? 0);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }

    /**
     * Whether the user owns a business (businesses.owner_id). Only a
     * super-admin may demote, suspend or delete an owner.
     */
    public function isBusinessOwner(): bool
    {
        return Business::query()->where('owner_id', $this->getKey())->exists();
    }

    /**
     * Whether this user owns the business the given user belongs to.
     */
    public function ownsBusinessOf(User $user): bool
    {
        return $user->business_id !== null
            && Business::query()->whereKey($user->business_id)->where('owner_id', $this->getKey())->exists();
    }

    /**
     * Whether the user is a shop owner.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    /**
     * Give an admin their own business if they have none yet, so the shops,
     * suppliers and staff they create have somewhere to belong.
     */
    public function ensureBusiness(): ?Business
    {
        if ($this->business_id !== null || ! $this->isAdmin()) {
            return $this->business;
        }

        $business = Business::create([
            'name' => __(':name\'s Business', ['name' => $this->name]),
            'owner_id' => $this->id,
        ]);

        $this->forceFill(['business_id' => $business->id])->saveQuietly();

        // Role and permission assignments are recorded against the user's
        // business (Spatie teams); move any made before it existed
        foreach ([config('permission.table_names.model_has_roles'), config('permission.table_names.model_has_permissions')] as $pivot) {
            DB::table($pivot)
                ->where('model_type', $this->getMorphClass())
                ->where(config('permission.column_names.model_morph_key'), $this->getKey())
                ->update(['business_id' => $business->id]);
        }

        self::forgetShopAccessCache();

        return $business;
    }

    /**
     * Get the shop IDs explicitly assigned to the user (the shop_user pivot).
     * For filtering data use accessibleShopIds(), which also covers admins.
     *
     * @return Collection<int, int>
     */
    public function assignedShopIds(): Collection
    {
        if ($this->relationLoaded('shops')) {
            return $this->shops->pluck('id')->map(fn ($shopId): int => (int) $shopId)->sort()->values();
        }

        return $this->shops()
            ->withoutGlobalScope(ShopAccessScope::class)
            ->orderBy('shops.id')
            ->pluck('shops.id')
            ->map(fn ($shopId): int => (int) $shopId)
            ->values();
    }

    /**
     * The shops whose data this user may see: every shop for a super-admin,
     * every shop of their business for an admin, their assigned shops for
     * everyone else. Memoised per request because every scoped query asks.
     *
     * @return Collection<int, int>
     */
    public function accessibleShopIds(): Collection
    {
        $key = self::SHOP_ACCESS_CACHE_PREFIX.$this->getKey();
        $attributes = request()->attributes;

        if (! $attributes->has($key)) {
            $attributes->set($key, $this->resolveAccessibleShopIds());
        }

        return $attributes->get($key);
    }

    /**
     * The business whose shared lists (suppliers, categories...) this user
     * works with: their own, or for legacy staff rows without one, the
     * business of the first shop they are assigned to.
     */
    public function currentBusinessId(): ?int
    {
        if ($this->business_id !== null) {
            return (int) $this->business_id;
        }

        $shopIds = $this->accessibleShopIds();

        if ($shopIds->isEmpty()) {
            return null;
        }

        $businessId = Shop::withoutGlobalScope(ShopAccessScope::class)
            ->whereIn('id', $shopIds->all())
            ->whereNotNull('business_id')
            ->orderBy('id')
            ->value('business_id');

        return $businessId === null ? null : (int) $businessId;
    }

    /**
     * Drop the memoised shop access, after a shop is created or deleted or a
     * user's shop assignment changes.
     */
    public static function forgetShopAccessCache(): void
    {
        $attributes = request()->attributes;

        foreach (array_keys($attributes->all()) as $key) {
            if (str_starts_with((string) $key, self::SHOP_ACCESS_CACHE_PREFIX)) {
                $attributes->remove($key);
            }
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function resolveAccessibleShopIds(): Collection
    {
        $shops = Shop::withoutGlobalScope(ShopAccessScope::class);

        // Ordered by id: the first one is a user's default shop (shop_id), so
        // it must not depend on whatever order the database returns rows in
        if ($this->isSuperAdmin()) {
            $ids = $shops->orderBy('id')->pluck('id');
        } elseif ($this->isAdmin()) {
            $ids = $this->business_id === null
                ? collect()
                : $shops->where('business_id', $this->business_id)->orderBy('id')->pluck('id');
        } else {
            $ids = $this->assignedShopIds();
        }

        return $ids->map(fn ($shopId): int => (int) $shopId)->values();
    }

    /**
     * Users the viewer may see and manage: everyone for a super-admin; for
     * anyone else, the users of their own business, never a super-admin.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isSuperAdmin()) {
            return $query;
        }

        $businessId = $viewer->currentBusinessId();

        if ($businessId === null) {
            return $query->whereKey($viewer->getKey());
        }

        return $query
            ->where($this->qualifyColumn('business_id'), $businessId)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', Role::SUPER_ADMIN));
    }

    /**
     * Everyone except a super-admin is limited to their accessible shops.
     * (This used to mean "has at least one assigned shop", which let a user
     * with no shop see every shop.)
     */
    public function hasShopRestrictions(): bool
    {
        return ! $this->isSuperAdmin();
    }

    public function canAccessShop(?int $shopId): bool
    {
        if (! $this->hasShopRestrictions()) {
            return true;
        }

        // Rows with no shop are not owned by any business, so only a
        // super-admin may see them.
        if ($shopId === null) {
            return false;
        }

        return $this->accessibleShopIds()->contains((int) $shopId);
    }

    /**
     * Get the user's primary shop ID: their first assigned shop, else the
     * first shop they can access (how an admin gets a default shop).
     */
    public function getShopIdAttribute(): ?int
    {
        return $this->assignedShopIds()->first() ?? $this->accessibleShopIds()->first();
    }

    /**
     * Relationship: Created by user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Updated by user
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
