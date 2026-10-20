<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * A business's prepaid AI credit (Phase 4). Changed only by AiCredits, with
 * conditional single-statement updates so concurrent calls can't take it
 * below zero.
 */
class AiCreditBalance extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'balance', 'exhausted_notified_at'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:4', 'exhausted_notified_at' => 'datetime'];
    }
}
