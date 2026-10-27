<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * A record the till must drop on its next pull (Phase 7, decision 4.5):
 * deleted or soft-deleted, both count. Written by RecordsSyncDeletion.
 */
class SyncDeletion extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $fillable = ['business_id', 'entity', 'entity_id', 'entity_uuid', 'deleted_at'];

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }
}
