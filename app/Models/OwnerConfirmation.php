<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * A one-time code emailed to an owner to confirm something only an owner may
 * do (stage 3): closing the business, and handing it over. Only the code's
 * hash is stored; it expires, and a few wrong tries use it up.
 */
class OwnerConfirmation extends Model
{
    use BelongsToBusiness;

    public const PURPOSE_CLOSE = 'close';

    public const PURPOSE_HANDOVER = 'handover';

    public const MAX_ATTEMPTS = 5;

    public const EXPIRES_AFTER_MINUTES = 15;

    protected $fillable = [
        'business_id',
        'user_id',
        'purpose',
        'code_hash',
        'payload',
        'attempts',
        'expires_at',
        'used_at',
    ];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->attempts < self::MAX_ATTEMPTS && $this->expires_at->isFuture();
    }

    /**
     * Checks $code, counting a wrong try; a right one uses the code up.
     */
    public function attempt(string $code): bool
    {
        if (! $this->isUsable()) {
            return false;
        }

        if (! Hash::check($code, $this->code_hash)) {
            $this->increment('attempts');

            return false;
        }

        $this->update(['used_at' => now()]);

        return true;
    }
}
