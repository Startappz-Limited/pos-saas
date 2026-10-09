<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function canAccessShop(?int $shopId): bool
    {
        if ($shopId === null) {
            return ! $this->hasShopRestrictions();
        }

        if (! $this->hasShopRestrictions()) {
            return true;
        }

        return $this->assignedShopIds()->contains((int) $shopId);
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
