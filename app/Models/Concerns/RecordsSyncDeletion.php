<?php

namespace App\Models\Concerns;

use App\Models\SyncDeletion;
use Illuminate\Database\Eloquent\Model;

/**
 * Logs a deleted record for the till's sync pull (Phase 7, decision 4.5): a
 * soft delete counts as a delete. The model names its sync entity with
 * `syncEntity()`; restoring a soft-deleted record touches it, so the next pull
 * sends it again.
 */
trait RecordsSyncDeletion
{
    public static function bootRecordsSyncDeletion(): void
    {
        static::deleted(function (Model $model): void {
            if (empty($model->business_id)) {
                return;
            }

            SyncDeletion::create([
                'business_id' => $model->business_id,
                'entity' => $model->syncEntity(),
                'entity_id' => $model->getKey(),
                'entity_uuid' => $model->uuid ?? null,
                'deleted_at' => now(),
            ]);
        });
    }

    /**
     * The name the sync pull uses for this kind of record.
     */
    abstract public function syncEntity(): string;
}
