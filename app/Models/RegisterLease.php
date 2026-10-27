<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A lease a till was given to trade offline until `trade_until` (Phase 7,
 * decision 4.5). Written only by App\Services\Sync\TradingLease.
 */
class RegisterLease extends Model
{
    use BelongsToBusiness;
    use BelongsToShop;

    protected $fillable = [
        'uuid',
        'business_id',
        'shop_id',
        'register_id',
        'issued_to',
        'issued_at',
        'trade_until',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'trade_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $lease): void {
            $lease->uuid ??= (string) Str::uuid();
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
}
