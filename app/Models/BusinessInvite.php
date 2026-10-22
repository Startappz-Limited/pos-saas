<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An invitation to join a business (decision 4.4, stage 3), with the role and
 * shops the person gets on accepting. The link carries a random token; only
 * its hash is stored.
 */
class BusinessInvite extends Model
{
    use Auditable;
    use BelongsToBusiness;
    use HasFactory;

    public const EXPIRES_AFTER_HOURS = 72;

    protected $fillable = [
        'business_id',
        'email',
        'name',
        'role_id',
        'shop_ids',
        'token_hash',
        'invited_by',
        'expires_at',
        'accepted_at',
        'accepted_by',
        'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'shop_ids' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invite): void {
            if (empty($invite->uuid)) {
                $invite->uuid = (string) Str::uuid();
            }
            $invite->email = Str::lower($invite->email);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * The invite a link's token names, in whichever business it belongs to:
     * the person opening it is usually signed out, or signed in to another
     * business.
     */
    public static function findByToken(string $token): ?self
    {
        return static::query()->withoutGlobalScopes()->where('token_hash', static::hashToken($token))->first();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
