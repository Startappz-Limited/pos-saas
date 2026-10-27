<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * An item a till pushed and the server applied (Phase 7): unique per business
 * and client uuid, so pushing it again returns this result instead of doing
 * it twice. Written only by App\Services\Sync\SyncPush.
 */
class SyncPushReceipt extends Model
{
    use BelongsToBusiness;

    protected $fillable = ['business_id', 'register_id', 'client_uuid', 'type', 'result', 'applied_at'];

    protected function casts(): array
    {
        return ['result' => 'array', 'applied_at' => 'datetime'];
    }
}
