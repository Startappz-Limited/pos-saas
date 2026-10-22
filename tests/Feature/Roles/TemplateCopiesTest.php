<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TwoBusinesses;

/**
 * Copies of the role templates, Phase 5 (A2): linked to their template, never
 * deleted or renamed by a business, and reset to the template's current
 * permissions on request, in one business only.
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->tenant->forget();
});

/**
 * Business $business's own copy of the template $name.
 */
function copyOf(Business $business, string $name): Role
{
    return Role::withoutGlobalScopes()->where('business_id', $business->id)->where('name', $name)->firstOrFail();
}

/**
 * @return array<int, string>
 */
function permissionNames(Role $role): array
{
    return $role->fresh()->permissions->pluck('name')->sort()->values()->all();
}

it('gives a new business a copy of each template, linked to it', function () {
    $business = Business::factory()->create();

    $copies = Role::withoutGlobalScopes()->where('business_id', $business->id)->get()->keyBy('name');
    $templates = Role::withoutGlobalScopes()->whereNull('business_id')->pluck('id', 'name');

    expect($copies->keys()->sort()->values()->all())->toBe(['accountant', 'cashier', 'customer', 'inventory-clerk', 'manager']);
    foreach ($copies as $name => $copy) {
        expect($copy->template_id)->toBe($templates[$name]);
    }
});

it('links existing copies, and restores a deleted copy with its holders, when migrated', function () {
    $business = $this->fixture->a->business;
    $accountant = User::factory()->memberOf($business)->create();

    // Before Phase 5: copies without a link, and a business that deleted its
    // accountant copy and gave the shared template to a member instead.
    DB::table('roles')->where('business_id', $business->id)->update(['template_id' => null]);
    DB::table('roles')->where('business_id', $business->id)->where('name', 'accountant')->delete();
    $template = DB::table('roles')->whereNull('business_id')->where('name', 'accountant')->value('id');
    DB::table('model_has_roles')->insert(['role_id' => $template, 'model_type' => User::class, 'model_id' => $accountant->id, 'business_id' => $business->id]);

    // The data step only: the column exists already (a schema change would
    // commit the test's transaction on MySQL).
    $migration = require database_path('migrations/2026_10_22_000001_link_role_copies_to_templates.php');
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $restored = copyOf($business, 'accountant');
    expect(Role::withoutGlobalScopes()->where('business_id', $business->id)->whereNull('template_id')->count())->toBe(0)
        ->and($restored->template_id)->toBe($template)
        ->and(permissionNames($restored))->toBe(permissionNames(Role::withoutGlobalScopes()->find($template)))
        ->and(DB::table('model_has_roles')->where('role_id', $template)->where('business_id', $business->id)->count())->toBe(0)
        ->and($this->tenant->run($business, fn () => $accountant->fresh()->roles->sole()->id))->toBe($restored->id)
        // The other business is unchanged.
        ->and(copyOf($this->fixture->b->business, 'accountant')->template_id)->toBe($template);
});

it('refuses deleting a copy, even by the owner, with a reason', function () {
    $copy = copyOf($this->fixture->a->business, 'manager');

    $this->actingAs($this->fixture->a->owner)->delete(route('roles.destroy', $copy))->assertForbidden()
        ->assertSee('A built-in role can&#039;t be deleted. You can reset it to default instead.', false);

    expect(Role::withoutGlobalScopes()->whereKey($copy->id)->exists())->toBeTrue();
});

it('refuses renaming a copy, but lets its permissions change', function () {
    $copy = copyOf($this->fixture->a->business, 'manager');
    $void = Permission::findByName('sales.void');

    $this->actingAs($this->fixture->a->owner)->put(route('roles.update', $copy), ['name' => 'head-manager', 'permissions' => [$void->id]])
        ->assertSessionHasErrors(['name' => 'A built-in role keeps its name.']);
    expect($copy->fresh()->name)->toBe('manager');

    $this->actingAs($this->fixture->a->owner)->put(route('roles.update', $copy), ['name' => 'manager', 'permissions' => [$void->id]])
        ->assertSessionHasNoErrors();
    expect(permissionNames($copy))->toBe(['sales.void']);
});

it('resets a copy to the template\'s current permissions, in that business only, and audits it', function () {
    $a = $this->fixture->a->business;
    $copyA = copyOf($a, 'manager');
    $copyB = copyOf($this->fixture->b->business, 'manager');
    $copyA->syncPermissions(['sales.void']);
    $copyB->syncPermissions(['sales.view']);
    // The template changed since the copies were made.
    $template = Role::withoutGlobalScopes()->find($copyA->template_id);
    $template->givePermissionTo('reports.full-access');

    $this->actingAs($this->fixture->a->owner)->post(route('roles.reset', $copyA), ['confirm' => '1'])
        ->assertRedirect(route('roles.show', $copyA))->assertSessionHas('success');

    $audit = AuditLog::query()->where('auditable_type', Role::class)->where('auditable_id', $copyA->id)->latest('id')->first();

    expect(permissionNames($copyA))->toBe(permissionNames($template))
        ->and(permissionNames($copyB))->toBe(['sales.view'])
        ->and($audit->new_values['reset_to_default'] ?? null)->toBeTrue()
        ->and($audit->old_values['permissions'])->toBe(['sales.void'])
        ->and($audit->business_id)->toBe($a->id);
});

it('asks for the confirmation before resetting', function () {
    $copy = copyOf($this->fixture->a->business, 'manager');
    $copy->syncPermissions(['sales.void']);

    $this->actingAs($this->fixture->a->owner)->post(route('roles.reset', $copy))
        ->assertSessionHasErrors(['confirm' => 'Confirm that you want to reset this role to default.']);
    $this->actingAs($this->fixture->a->owner)->post(route('roles.reset', $copy), ['confirm' => '0'])->assertSessionHasErrors('confirm');

    expect(permissionNames($copy))->toBe(['sales.void']);
});

it('refuses resetting a custom role, the owner role or a shared template', function () {
    $custom = $this->tenant->run($this->fixture->a->business, fn () => Role::create(['name' => 'helper', 'guard_name' => 'web', 'business_id' => $this->fixture->a->business->id]));
    $owner = $this->fixture->a->owner;

    $this->actingAs($owner)->post(route('roles.reset', $custom), ['confirm' => '1'])->assertForbidden()
        ->assertSee('Only a built-in role can be reset to default.');
    $this->actingAs($owner)->post(route('roles.reset', sharedRole('admin')), ['confirm' => '1'])->assertForbidden();
});

it('lets a non-owner reset a copy only to permissions they hold', function () {
    $a = $this->fixture->a->business;
    $copy = copyOf($a, 'customer');
    $copy->syncPermissions([]);
    $templatePermissions = Role::withoutGlobalScopes()->find($copy->template_id)->permissions->pluck('name')->all();

    $editor = $this->tenant->run($a, function () use ($a): User {
        $role = Role::create(['name' => 'role-editor', 'guard_name' => 'web', 'business_id' => $a->id]);
        $role->syncPermissions(['roles.view', 'roles.update']);
        $user = User::factory()->memberOf($a)->create();
        $user->assignRole($role);

        return $user;
    });

    $this->actingAs($editor)->post(route('roles.reset', $copy), ['confirm' => '1'])->assertForbidden();
    expect(permissionNames($copy))->toBe([]);

    $this->tenant->run($a, fn () => Role::findByName('role-editor')->givePermissionTo($templatePermissions));
    $this->actingAs($editor)->post(route('roles.reset', $copy), ['confirm' => '1'])->assertSessionHas('success');
    expect(permissionNames($copy))->toBe(collect($templatePermissions)->sort()->values()->all());
});

it('shows Reset to default and no Delete on a copy\'s page, and the reverse on a custom role\'s', function () {
    $owner = $this->fixture->a->owner;
    $copy = copyOf($this->fixture->a->business, 'manager');
    $custom = $this->tenant->run($this->fixture->a->business, fn () => Role::create(['name' => 'helper', 'guard_name' => 'web', 'business_id' => $this->fixture->a->business->id]));
    $this->tenant->forget();

    $this->actingAs($owner)->get(route('roles.show', $copy))->assertOk()
        ->assertSee(route('roles.reset', $copy))->assertSee('data-swal-confirm', false)->assertSee('Built-in role')
        ->assertDontSee('deleteRoleModal', false);
    $this->tenant->forget();
    $this->actingAs($owner)->get(route('roles.show', $custom))->assertOk()
        ->assertDontSee(route('roles.reset', $custom))->assertSee('deleteRoleModal', false)->assertSee('Custom Role');
});
