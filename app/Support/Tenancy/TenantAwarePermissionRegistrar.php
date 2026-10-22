<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spatie's permission cache is shared by every business, but it loads each
 * permission's roles through the Role model, whose scope shows only the shared
 * templates and the current business's own roles. Whichever business's request
 * built the cache would then be the only business whose own roles (its copies
 * of the templates, its custom roles) granted anything; every other business's
 * staff lost their role permissions until the cache was cleared (found in
 * Phase 5). The cache is therefore built with the scopes lifted: it holds
 * every role, and a user still matches only the roles they hold in the
 * current business (Spatie teams).
 */
class TenantAwarePermissionRegistrar extends PermissionRegistrar
{
    protected function getPermissionsWithRoles(): Collection
    {
        return app(TenantContext::class)->bypass(fn (): Collection => parent::getPermissionsWithRoles());
    }
}
