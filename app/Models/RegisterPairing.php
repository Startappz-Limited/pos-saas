<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One device's time on a register (Phase 7, decision 4.5): which device,
 * under which register code, paired by whom, until when. A register has at
 * most one open pairing; re-pairing ends it with `repaired`. Audited.
 */
class RegisterPairing extends Model
{
    use Auditable;
    use BelongsToBusiness;
    use BelongsToShop;

    public const END_REPAIRED = 'repaired';

    protected $fillable = [
        'uuid',
        'business_id',
        'shop_id',
        'register_id',
        'device_id',
        'device_name',
        'code',
        'paired_by',
        'paired_at',
        'ended_at',
        'end_reason',
    ];

    protected function casts(): array
    {
        return [
            'paired_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $pairing): void {
            $pairing->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function pairedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paired_by');
    }
}
