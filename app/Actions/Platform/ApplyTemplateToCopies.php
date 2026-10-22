<?php

namespace App\Actions\Platform;

use App\Enums\AuditEvent;
use App\Models\Role;
use App\Support\Platform\PlatformAudit;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * "Apply to all copies" (Phase 5, B6): every business's copy of a template
 * takes the template's current permissions, a platform-wide change made only
 * on an explicit, confirmed request. One audit row with the number of copies.
 */
class ApplyTemplateToCopies
{
    /**
     * @return int how many copies were changed
     */
    public function execute(Role $template): int
    {
        if (! $template->isShared() || $template->isOwnerRole()) {
            throw new DomainException(__('Only a copied role template can be applied to its copies.'));
        }

        $permissionIds = $template->permissions()->pluck('permissions.id');

        // Every business's copy: platform-wide on purpose, the console's only
        // write across businesses (listed in TenantScopeBypassTest).
        $copies = app(TenantContext::class)->bypass(fn () => Role::query()->where('template_id', $template->id)->get());

        DB::transaction(function () use ($copies, $permissionIds): void {
            foreach ($copies as $copy) {
                $copy->permissions()->sync($permissionIds);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        PlatformAudit::record('role_template.applied_to_copies', null, [
            'template' => $template->name,
            'copies' => $copies->count(),
            'permissions' => $permissionIds->count(),
        ], AuditEvent::UPDATED);

        return $copies->count();
    }
}
