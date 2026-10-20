<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use App\Support\Tenancy\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Auditable;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * The column's default, so a login just created reads as active before
     * it's reloaded (the account status is checked on every request, stage 3).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

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
        'default_business_id',
        'created_by',
        'updated_by',
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
            // Never fillable: only the platform grants it.
            'is_platform_operator' => 'boolean',
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
     * Only members of the current business bind (Phase 2): users carry no
     * tenant scope, and business owners pass every policy, so another
     * business's user was open to view, change and delete by id. Now a 404.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->newQuery()
            ->inCurrentBusiness()
            ->where($this->qualifyColumn($field ?? $this->getRouteKeyName()), $value)
            ->first();
    }

    /**
     * Scope: members of the current business. Users carry no tenant scope
     * (one login can belong to several businesses), so every user listing
     * applies this (stage 1c). With no business set, nobody.
     */
    public function scopeInCurrentBusiness($query)
    {
        return $query->whereHas('memberships', fn ($membership) => $membership->where('business_id', app(TenantContext::class)->id() ?? 0));
    }

    /**
     * Adds `business_status`: the user's status in the current business
     * (stage 3), which is what a business's screens and the API show.
     */
    public function scopeWithBusinessStatus($query)
    {
        return $query->addSelect(['business_status' => BusinessMembership::query()->select('status')
            ->whereColumn('business_user.user_id', 'users.id')
            ->where('business_id', app(TenantContext::class)->id() ?? 0)
            ->limit(1)]);
    }

    /**
     * Members with $status in the current business.
     */
    public function scopeWhereBusinessStatus($query, UserStatus|string $status)
    {
        $status = $status instanceof UserStatus ? $status->value : $status;

        return $query->whereHas('memberships', fn ($membership) => $membership
            ->where('business_id', app(TenantContext::class)->id() ?? 0)
            ->where('status', $status));
    }

    /**
     * The user's status in the current business (stage 3): suspending or
     * deactivating someone from a business's screens changes only this. The
     * account's own `status` is the platform operator's (Phase 5).
     */
    public function businessStatus(): ?UserStatus
    {
        $status = $this->getAttribute('business_status')
            ?? $this->memberships()->where('business_id', app(TenantContext::class)->id() ?? 0)->value('status');

        return $status === null ? null : UserStatus::tryFrom($status);
    }

    /**
     * For a response from a business's screens or the API: `status` shows the
     * status in the current business, keeping the field the Flutter app reads.
     * Never save the model after this.
     */
    public function presentBusinessStatus(): static
    {
        $this->setAttribute('status', $this->businessStatus() ?? $this->status);

        return $this;
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
            // Private since stage 1c ({business uuid}/profile-photos/...),
            // served to members of that business; public before.
            return str_contains($this->profile_photo, '/profile-photos/')
                ? route('tenant-files.show', ['path' => $this->profile_photo])
                : asset('storage/'.$this->profile_photo);
        }

        // Default avatar using UI Avatars
        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&color=7F9CF5&background=EBF4FF';
    }

    /**
     * Relationship: Shops the user belongs to
     */
    /**
     * The businesses this login belongs to (decision 4.4).
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class)
            ->using(BusinessMembership::class)
            ->withPivot(['status', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    /**
     * The business a sign-in opens when it's still usable.
     */
    public function defaultBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'default_business_id');
    }

    /**
     * Whether this login has an active membership in an active business.
     */
    public function canEnterBusiness(Business|int|null $business): bool
    {
        $businessId = $business instanceof Business ? $business->id : $business;

        if ($businessId === null) {
            return false;
        }

        return $this->memberships()
            ->where('business_id', $businessId)
            ->where('status', BusinessMembership::STATUS_ACTIVE)
            ->whereHas('business', fn ($query) => $query->where('status', Business::STATUS_ACTIVE))
            ->exists();
    }

    /**
     * The business a sign-in opens (decision 4.4): the usable default, else the
     * only usable business; null when the user has several and no usable
     * default (they pick one), or none.
     */
    public function businessToOpen(): ?int
    {
        if ($this->canEnterBusiness($this->default_business_id)) {
            return $this->default_business_id;
        }

        $usable = $this->usableBusinesses();

        return $usable->count() === 1 ? $usable->first()->id : null;
    }

    /**
     * The businesses this login can work in now: an active membership in an
     * active business, by name.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Business>
     */
    public function usableBusinesses(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->businesses()
            ->wherePivot('status', BusinessMembership::STATUS_ACTIVE)
            ->where('businesses.status', Business::STATUS_ACTIVE)
            ->orderBy('businesses.name')
            ->get();
    }

    /**
     * Adds an active membership in $business, and makes it the default
     * business when the user has none.
     */
    public function joinBusiness(Business $business, ?User $invitedBy = null): BusinessMembership
    {
        $membership = BusinessMembership::firstOrCreate(
            ['business_id' => $business->id, 'user_id' => $this->id],
            ['status' => BusinessMembership::STATUS_ACTIVE, 'invited_by' => $invitedBy?->id, 'joined_at' => now()],
        );

        if ($this->default_business_id === null) {
            $this->forceFill(['default_business_id' => $business->id])->saveQuietly();
        }

        return $membership;
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_user')
            ->withTimestamps();
    }

    /**
     * Get the shop IDs explicitly assigned to the user.
     *
     * @return Collection<int, int>
     */
    public function assignedShopIds(): Collection
    {
        if ($this->relationLoaded('shops')) {
            return $this->shops->pluck('id')->map(fn ($shopId): int => (int) $shopId)->values();
        }

        return $this->shops()->pluck('shops.id')->map(fn ($shopId): int => (int) $shopId)->values();
    }

    public function hasShopRestrictions(): bool
    {
        if ($this->relationLoaded('shops')) {
            return $this->shops->isNotEmpty();
        }

        return $this->shops()->exists();
    }

    /**
     * Whether the user may work in $shopId: one of the current business's
     * shops they may access (TenantContext::accessibleShopIdsFor). With no
     * business set, no shop; a member with no assigned shop, none.
     */
    public function canAccessShop(?int $shopId): bool
    {
        // "Every shop" (no shop named): only an owner (default-deny, 4.4).
        if ($shopId === null) {
            return app(TenantContext::class)->has() && $this->isBusinessOwner();
        }

        $tenant = app(TenantContext::class);

        return $tenant->has() && in_array((int) $shopId, $tenant->accessibleShopIdsFor($this), true);
    }

    /**
     * An owner sees every shop of the business (decision 4.4). Owners hold
     * the admin role in the business; a business may have several.
     */
    public function isBusinessOwner(): bool
    {
        // The owner role, held in the current business (decision 4.4).
        return app(TenantContext::class)->has() && $this->hasRole('admin');
    }

    /**
     * The founding owner of the current business (stage 3).
     */
    public function isPrimaryOwner(): bool
    {
        $business = app(TenantContext::class)->business();

        return $business !== null && $business->primary_owner_id === $this->id;
    }

    /**
     * The platform operator (decision 4.4): global, never a business member,
     * the only user Gate::before lets do everything.
     */
    public function isPlatformOperator(): bool
    {
        return (bool) $this->is_platform_operator;
    }

    /**
     * Get the user's primary shop ID.
     */
    public function getShopIdAttribute(): ?int
    {
        return $this->shops()->first()?->id;
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
