<?php

namespace App\Listeners;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Audits role and permission grants and revocations.
 *
 * These are pivot-table changes, so no model event fires on the user — Spatie's own
 * events are the only hook. Auditing authorization changes is mandatory
 * (.ai/general/0.5 audit_log_guide.md §5.5).
 */
class AuditAuthorizationChanges
{
    public function handleRoleAttached(RoleAttached $event): void
    {
        $this->record($event->model, AuditEvent::UPDATED, 'roles_attached', $this->resolveRoleNames($event->rolesOrIds));
    }

    public function handleRoleDetached(RoleDetached $event): void
    {
        $this->record($event->model, AuditEvent::UPDATED, 'roles_detached', $this->resolveRoleNames($event->rolesOrIds));
    }

    public function handlePermissionAttached(PermissionAttached $event): void
    {
        $this->record($event->model, AuditEvent::UPDATED, 'permissions_attached', $this->resolvePermissionNames($event->permissionsOrIds));
    }

    public function handlePermissionDetached(PermissionDetached $event): void
    {
        $this->record($event->model, AuditEvent::UPDATED, 'permissions_detached', $this->resolvePermissionNames($event->permissionsOrIds));
    }

    /**
     * @param  array<int, string>  $names
     */
    private function record(Model $model, AuditEvent $auditEvent, string $key, array $names): void
    {
        if ($names === []) {
            return;
        }

        AuditableEvent::dispatch(
            auditableType: $model::class,
            auditableId: $model->getKey() !== null ? (int) $model->getKey() : null,
            auditableUuid: isset($model->getAttributes()['uuid']) ? (string) $model->getAttributes()['uuid'] : null,
            event: $auditEvent,
            oldValues: null,
            newValues: [$key => $names],
            userId: auth()->id(),
            userName: auth()->user()?->name,
            userEmail: auth()->user()?->email,
            tags: ['authorization'],
        );
    }

    /**
     * Spatie passes ids, models, or a collection depending on the call path — resolve
     * to human-readable names so the trail is readable without joins into another
     * database.
     *
     * @return array<int, string>
     */
    private function resolveRoleNames(mixed $rolesOrIds): array
    {
        return $this->resolveNames($rolesOrIds, Role::class);
    }

    /**
     * @return array<int, string>
     */
    private function resolvePermissionNames(mixed $permissionsOrIds): array
    {
        return $this->resolveNames($permissionsOrIds, Permission::class);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    private function resolveNames(mixed $value, string $modelClass): array
    {
        if ($value === null) {
            return [];
        }

        $items = $value instanceof Collection ? $value->all() : (is_array($value) ? $value : [$value]);
        $names = [];
        $ids = [];

        foreach ($items as $item) {
            if ($item instanceof Model) {
                $names[] = (string) ($item->name ?? $item->getKey());

                continue;
            }

            $ids[] = $item;
        }

        if ($ids !== []) {
            // Configured model, so a project subclass (App\Models\Role) is respected.
            $resolved = app($modelClass)::query()->whereKey($ids)->pluck('name')->all();
            $names = array_merge($names, array_map('strval', $resolved));
        }

        return array_values(array_filter($names, fn ($n) => $n !== ''));
    }
}
