<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A person's till PIN in a business (Phase 7), for signing in on a till while
 * it is offline. Only its hash is stored. Setting, changing and resetting it
 * are audited; the hash itself never reaches the audit trail.
 */
class TillPin extends Model
{
    use Auditable;
    use BelongsToBusiness;

    protected $fillable = ['uuid', 'business_id', 'user_id', 'pin_hash', 'set_at'];

    /** @var list<string> */
    protected array $auditExclude = ['pin_hash'];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return ['set_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $pin): void {
            $pin->uuid ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
