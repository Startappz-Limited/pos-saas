<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Cash taken out of an open session (stage 10, step 4): a void's cash
 * refund, a refund owed paid later, or petty cash recorded by hand. The
 * session's expected cash drops by it.
 */
class CashPayout extends Model
{
    use Auditable;
    use BelongsToShop;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'shop_id',
        'cash_register_id',
        'amount',
        'reason',
        'sale_id',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (CashPayout $payout): void {
            $payout->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
