<?php

namespace App\Models;

use App\Enums\SocialPlatform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class SocialAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'shop_id',
        'platform',
        'account_name',
        'external_account_id',
        'username',
        'avatar_url',
        'credentials',
        'metadata',
        'is_active',
        'connected_at',
        'token_expires_at',
        'last_synced_at',
        'last_error',
        'connected_by',
    ];

    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'metadata' => 'array',
            'is_active' => 'boolean',
            'connected_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $account): void {
            if (empty($account->uuid)) {
                $account->uuid = (string) Str::uuid();
            }
            if (auth()->check() && empty($account->connected_by)) {
                $account->connected_by = auth()->id();
            }
            if (empty($account->connected_at)) {
                $account->connected_at = now();
            }
        });
    }

    // Relationships

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CampaignPost::class);
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForShop(Builder $query, int $shopId): Builder
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeForPlatform(Builder $query, SocialPlatform $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    // Encrypted credential helpers

    /**
     * Set credentials as encrypted JSON.
     *
     * @param  array<string, mixed>|null  $credentials
     */
    public function setCredentials(?array $credentials): void
    {
        $this->attributes['credentials'] = $credentials === null
            ? null
            : Crypt::encryptString(json_encode($credentials));
    }

    /**
     * Get decrypted credentials.
     *
     * @return array<string, mixed>
     */
    public function getCredentials(): array
    {
        $raw = $this->attributes['credentials'] ?? null;

        if (empty($raw)) {
            return [];
        }

        try {
            return (array) json_decode(Crypt::decryptString($raw), true);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getCredential(string $key, mixed $default = null): mixed
    {
        return $this->getCredentials()[$key] ?? $default;
    }

    public function hasValidToken(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->token_expires_at && $this->token_expires_at->isPast()) {
            return false;
        }

        return ! empty($this->getCredential('access_token'));
    }
}
