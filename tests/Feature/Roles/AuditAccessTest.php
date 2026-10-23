<?php

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Support\Navigation\Sidebar;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Who sees the audit log (fix/notices-and-sidebar-ui): only business owners by
 * default. An owner may give a role in their own business the audit
 * permissions; that reaches nobody in another business. The old
 * `audit-logs.*` permissions, never checked, are retired.
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->tenant = app(TenantContext::class);
    [$this->a, $this->b] = [Business::factory()->create(), Business::factory()->create()];

    $member = function (Business $business, string $role): User {
        return $this->tenant->run($business, function () use ($business, $role): User {
            $user = User::factory()->memberOf($business)->create();
            $user->assignRole($role);

            return $user;
        });
    };
    $this->ownerA = $member($this->a, 'admin');
    $this->managerA = $member($this->a, 'manager');
    $this->accountantA = $member($this->a, 'accountant');
    $this->managerB = $member($this->b, 'manager');
    $this->tenant->forget();
});

function auditMenu(User $user, Business $business): bool
{
    return app(TenantContext::class)->run($business, fn (): bool => collect(Sidebar::for($user))
        ->flatMap(fn (array $section) => $section['items'])->contains(fn (array $item) => ($item['route'] ?? null) === 'audit-logs.index'));
}

it('lets only the owner open the audit log and see it in the menu', function () {
    $this->actingAs($this->ownerA)->get(route('audit-logs.index'))->assertOk();

    foreach ([$this->managerA, $this->accountantA] as $user) {
        $this->tenant->forget();
        $this->actingAs($user)->get(route('audit-logs.index'))->assertForbidden();
        $this->actingAs($user)->post(route('audit-logs.export'))->assertForbidden();
    }

    expect(auditMenu($this->ownerA, $this->a))->toBeTrue()
        ->and(auditMenu($this->managerA, $this->a))->toBeFalse()
        ->and(auditMenu($this->accountantA, $this->a))->toBeFalse();
});

it('seeds no audit permission on the templates, and no audit-logs permissions at all', function () {
    foreach (['manager', 'accountant', 'cashier', 'inventory-clerk', 'customer'] as $name) {
        $template = Role::withoutGlobalScopes()->whereNull('business_id')->where('name', $name)->firstOrFail();
        expect($template->permissions()->where('name', 'like', 'audit%')->count())->toBe(0, "{$name} holds an audit permission");
    }

    expect(sharedRole('admin')->permissions->pluck('name'))->toContain('audit.view')->toContain('audit.export')
        ->and(Permission::query()->where('name', 'like', 'audit-logs.%')->exists())->toBeFalse();
});

it('lets an owner give their manager the audit log, in that business only', function () {
    $copyA = Role::withoutGlobalScopes()->where('business_id', $this->a->id)->where('name', 'manager')->firstOrFail();
    $keep = $copyA->permissions()->pluck('permissions.id')->all();

    $this->actingAs($this->ownerA)->put(route('roles.update', $copyA), [
        'name' => 'manager',
        'permissions' => [...$keep, Permission::findByName('audit.view')->id],
    ])->assertSessionHasNoErrors();

    $this->tenant->forget();
    $this->actingAs($this->managerA)->get(route('audit-logs.index'))->assertOk();
    expect(auditMenu($this->managerA, $this->a))->toBeTrue();

    // Business B's manager is unaffected.
    $this->tenant->forget();
    $this->actingAs($this->managerB)->get(route('audit-logs.index'))->assertForbidden();
    expect(auditMenu($this->managerB, $this->b))->toBeFalse();
});

it('retires the old permissions: dropped from templates and copies, kept as working ones on owners\' roles and people', function () {
    $old = collect(['audit-logs.view', 'audit-logs.export', 'audit-logs.full-access'])
        ->mapWithKeys(fn (string $name) => [$name => Permission::create(['name' => $name, 'guard_name' => 'web'])]);

    $template = Role::withoutGlobalScopes()->whereNull('business_id')->where('name', 'manager')->firstOrFail();
    $copyA = Role::withoutGlobalScopes()->where('business_id', $this->a->id)->where('name', 'manager')->firstOrFail();
    $custom = $this->tenant->run($this->a, fn () => Role::create(['name' => 'auditor', 'guard_name' => 'web', 'business_id' => $this->a->id]));
    $template->givePermissionTo([$old['audit-logs.view'], $old['audit-logs.export']]);
    $copyA->givePermissionTo([$old['audit-logs.view'], $old['audit-logs.export']]);
    $custom->givePermissionTo($old['audit-logs.full-access']);
    $this->tenant->run($this->a, fn () => $this->accountantA->givePermissionTo($old['audit-logs.export']));

    $migration = require database_path('migrations/2026_10_24_000001_retire_audit_logs_permissions.php');
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $names = fn (Role $role) => $role->fresh()->permissions()->where('name', 'like', 'audit%')->pluck('name')->sort()->values()->all();
    expect($names($template))->toBe([])
        ->and($names($copyA))->toBe([])
        ->and($names($custom))->toBe(['audit.export', 'audit.view'])
        ->and(DB::table('model_has_permissions')->where('model_id', $this->accountantA->id)
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')->pluck('permissions.name')->all())->toBe(['audit.export'])
        ->and(Permission::query()->where('name', 'like', 'audit-logs.%')->exists())->toBeFalse()
        // The manager still can't open the audit log.
        ->and($this->tenant->run($this->a, fn () => $this->managerA->fresh()->can('audit.view')))->toBeFalse();
});
