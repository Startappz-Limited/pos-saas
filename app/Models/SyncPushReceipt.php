<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * An item a till pushed and the server applied (Phase 7): unique per business
 * and client uuid, so pushing it again returns this result instead of doing
 * it twice. Since stage 10 (step 5c) also an item settled on the till sync
 * review or discarded from the till: `outcome` is `applied`, `accepted` or
 * `discarded`. Written by App\Services\Sync\SyncPush and App\Support\Sync\HeldItems.
 */
class SyncPushReceipt extends Model
{
    use BelongsToBusiness;

    public const APPLIED = 'applied';

    public const ACCEPTED = 'accepted';

    public const DISCARDED = 'discarded';

    protected $fillable = ['business_id', 'register_id', 'client_uuid', 'type', 'result', 'outcome', 'applied_at'];

    protected function casts(): array
    {
        return ['result' => 'array', 'applied_at' => 'datetime'];
    }
}
