<?php

namespace App\Models\Concerns;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records create/update/delete on the using model to the audit trail.
 *
 * Add `use Auditable;` to a model and its lifecycle is captured automatically —
 * capture stays out of controllers and services, per
 * .claude/skills/laravel-audit/SKILL.md.
 *
 * A model may narrow what is captured with:
 *   protected array $auditExclude = ['some_noisy_column'];
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $model->dispatchAuditEvent(AuditEvent::CREATED, null, $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changes = $model->auditableAttributes($model->getChanges());

            // Nothing meaningful changed (e.g. only a timestamp) — don't create noise.
            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            $model->dispatchAuditEvent(AuditEvent::UPDATED, $original, $changes);
        });

        static::deleted(function (Model $model): void {
            $event = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? AuditEvent::FORCE_DELETED
                : AuditEvent::DELETED;

            $model->dispatchAuditEvent($event, $model->auditableAttributes($model->getAttributes()), null);
        });
    }

    /**
     * Dispatch an audit event for this model with the current actor attached.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function dispatchAuditEvent(AuditEvent $event, ?array $oldValues, ?array $newValues): void
    {
        $actor = Auth::user();

        AuditableEvent::dispatch(
            auditableType: static::class,
            auditableId: $this->getKey() !== null ? (int) $this->getKey() : null,
            auditableUuid: $this->auditableUuid(),
            event: $event,
            oldValues: $oldValues,
            newValues: $newValues,
            userId: $actor?->id,
            userName: $actor?->name,
            userEmail: $actor?->email,
            shopId: $this->auditShopId(),
        );
    }

    /**
     * Strip always-noisy and never-loggable columns.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        $excluded = array_merge(
            ['id', 'created_at', 'updated_at', 'password', 'remember_token'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : []
        );

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * The uuid of this record, when it has one.
     */
    protected function auditableUuid(): ?string
    {
        return isset($this->attributes['uuid']) ? (string) $this->attributes['uuid'] : null;
    }

    /**
     * Shop context for filtering. The audit table stores the integer shop_id — it
     * lives on another connection so there is no foreign key, but the id is stable
     * and cheap to filter on.
     */
    protected function auditShopId(): ?int
    {
        if (! isset($this->attributes['shop_id']) || $this->attributes['shop_id'] === null) {
            return null;
        }

        return (int) $this->attributes['shop_id'];
    }
}
