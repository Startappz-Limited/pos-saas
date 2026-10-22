<?php

use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TwoBusinesses;

/**
 * Roles per business, Phase 5 (A1): a business changes only its own roles;
 * shared templates and the owner role are read-only from a business, also
 * with `roles.full-access`; nobody raises their own permissions by editing a
 * role; names are unique within a business; holder counts are per business.
 */
beforeEach(function () {
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->tenant->forget();
});

/**
 * A member of business A holding a custom role with $permissions.
 *
 * @param  array<int, string>  $permissions
 */
function memberOfAWith(TwoBusinesses $fixture, array $permissions, string $roleName = 'role-editor'): User
{
    $tenant = app(TenantContext::class);

    return $tenant->run($fixture->a->business, function () use ($fixture, $permissions, $roleName): User {
        $role = Role::create(['name' => $roleName, 'guard_name' => 'web', 'business_id' => $fixture->a->business->id]);
        $role->syncPermissions($permissions);

        $user = User::factory()->memberOf($fixture->a->business)->create();
        $user->assignRole($role);

        return $user;
    });
}

/**
 * Business A's own role named $name, read in A.
 */
function roleOfA(TwoBusinesses $fixture, string $name): Role
{
    return app(TenantContext::class)->run($fixture->a->business, fn (): Role => Role::query()
        ->where('business_id', $fixture->a->business->id)->where('name', $name)->firstOrFail());
}

describe('shared templates and the owner role', function () {
    it('refuses every change to the owner role from a business, also to its owner', function () {
        $admin = sharedRole('admin');
        $permissionsBefore = $admin->permissions->pluck('id')->sort()->values()->all();

        $this->actingAs($this->fixture->a->owner)->put(route('roles.update', $admin), ['name' => 'admin', 'permissions' => []])
            ->assertForbidden();
        $this->actingAs($this->fixture->a->owner)->put(route('roles.updatePermissions', $admin), ['permissions' => []])
            ->assertForbidden();
        $this->actingAs($this->fixture->a->owner)->delete(route('roles.destroy', $admin))->assertForbidden();
        $this->actingAs($this->fixture->a->owner)->get(route('roles.edit', $admin))->assertForbidden();

        expect($admin->fresh()->permissions->pluck('id')->sort()->values()->all())->toBe($permissionsBefore);
    });

    it('refuses a shared template a business sees, and leaves every business\'s checks as they were', function () {
        // A template added after both businesses were created: neither has a
        // copy, so both see the shared row.
        seedPermissions();
        $template = sharedRole('stock-auditor');
        $template->syncPermissions(['inventory.view']);
        $holderInB = $this->tenant->run($this->fixture->b->business, function (): User {
            $user = User::factory()->memberOf($this->fixture->b->business)->create();
            $user->assignRole('stock-auditor');

            return $user;
        });

        $this->actingAs($this->fixture->a->owner)
            ->put(route('roles.update', $template), ['name' => 'stock-auditor', 'permissions' => [Permission::findByName('sales.void')->id]])
            ->assertForbidden();
        $this->actingAs($this->fixture->a->owner)->delete(route('roles.destroy', $template))->assertForbidden();

        expect($template->fresh()->permissions->pluck('name')->all())->toBe(['inventory.view'])
            ->and(Role::withoutGlobalScopes()->whereKey($template->id)->exists())->toBeTrue()
            ->and($this->tenant->run($this->fixture->b->business, fn () => $holderInB->fresh()->can('sales.void')))->toBeFalse()
            ->and($this->tenant->run($this->fixture->b->business, fn () => $holderInB->fresh()->can('inventory.view')))->toBeTrue();
    });

    it('lets roles.full-access alone change none of them', function () {
        seedPermissions();
        $fullAccess = memberOfAWith($this->fixture, ['roles.full-access']);
        $admin = sharedRole('admin');
        $template = sharedRole('stock-auditor');

        foreach ([$admin, $template] as $role) {
            $this->actingAs($fullAccess)->put(route('roles.update', $role), ['name' => 'renamed', 'permissions' => []])->assertForbidden();
            $this->actingAs($fullAccess)->delete(route('roles.destroy', $role))->assertForbidden();
            expect($role->fresh()->name)->not->toBe('renamed');
        }

        // It still counts as every roles.* permission on the business's own roles.
        $custom = $this->tenant->run($this->fixture->a->business, fn () => Role::create(['name' => 'helper', 'guard_name' => 'web', 'business_id' => $this->fixture->a->business->id]));
        $this->actingAs($fullAccess)->get(route('roles.index'))->assertOk();
        $this->actingAs($fullAccess)->put(route('roles.update', $custom), ['name' => 'helper-two', 'permissions' => []])
            ->assertRedirect(route('roles.index'));
        expect($custom->fresh()->name)->toBe('helper-two');
    });
});

describe('no escalation through role editing', function () {
    it('lets a non-owner give only permissions they hold', function () {
        seedPermissions();
        $editor = memberOfAWith($this->fixture, ['roles.view', 'roles.create', 'roles.update', 'products.view']);
        $products = Permission::findByName('products.view');
        $delete = Permission::findByName('products.delete');

        $this->actingAs($editor)->post(route('roles.store'), ['name' => 'stocker', 'permissions' => [$products->id, $delete->id]])
            ->assertSessionHasErrors(['permissions' => 'You can only give permissions you hold.']);
        expect(Role::withoutGlobalScopes()->where('name', 'stocker')->exists())->toBeFalse();

        $this->actingAs($editor)->post(route('roles.store'), ['name' => 'stocker', 'permissions' => [$products->id]])
            ->assertSessionHasNoErrors();
        $stocker = roleOfA($this->fixture, 'stocker');
        expect($stocker->permissions->pluck('name')->all())->toBe(['products.view']);

        $this->actingAs($editor)->put(route('roles.updatePermissions', $stocker), ['permissions' => [$delete->id]])
            ->assertSessionHasErrors('permissions');
        expect($stocker->fresh()->permissions->pluck('name')->all())->toBe(['products.view']);
    });

    it('keeps the permissions of a role that are out of the editor\'s reach', function () {
        seedPermissions();
        $editor = memberOfAWith($this->fixture, ['roles.view', 'roles.update', 'products.view']);
        $role = $this->tenant->run($this->fixture->a->business, function (): Role {
            $role = Role::create(['name' => 'supervisor', 'guard_name' => 'web', 'business_id' => $this->fixture->a->business->id]);
            $role->syncPermissions(['products.view', 'sales.void']);

            return $role;
        });

        // The editor sees only products.view and clears it.
        $this->actingAs($editor)->put(route('roles.update', $role), ['name' => 'supervisor', 'permissions' => []])
            ->assertSessionHasNoErrors();

        expect($role->fresh()->permissions->pluck('name')->all())->toBe(['sales.void']);
    });

    it('refuses a non-owner editing or deleting a role they hold', function () {
        seedPermissions();
        $editor = memberOfAWith($this->fixture, ['roles.view', 'roles.update', 'roles.delete', 'products.view']);
        $ownRole = roleOfA($this->fixture, 'role-editor');

        $this->actingAs($editor)->put(route('roles.update', $ownRole), ['name' => 'role-editor', 'permissions' => []])
            ->assertForbidden();
        $this->actingAs($editor)->put(route('roles.updatePermissions', $ownRole), ['permissions' => []])->assertForbidden();
        $this->actingAs($editor)->delete(route('roles.destroy', $ownRole))->assertForbidden();

        expect($ownRole->fresh()->permissions->pluck('name')->sort()->values()->all())
            ->toBe(['products.view', 'roles.delete', 'roles.update', 'roles.view']);
    });

    it('lets an owner edit any role of their business except the owner role', function () {
        $cashierCopy = roleOfA($this->fixture, 'cashier');
        $void = Permission::findByName('sales.void');

        $this->actingAs($this->fixture->a->owner)
            ->put(route('roles.update', $cashierCopy), ['name' => 'cashier', 'permissions' => [$void->id]])
            ->assertRedirect(route('roles.index'));

        expect($cashierCopy->fresh()->permissions->pluck('name')->all())->toBe(['sales.void'])
            // B's copy of the same template is untouched.
            ->and($this->tenant->run($this->fixture->b->business, fn () => Role::query()->where('business_id', $this->fixture->b->business->id)
                ->where('name', 'cashier')->first()->permissions->pluck('name')->all()))->not->toContain('sales.void');

        $this->actingAs($this->fixture->a->owner)->put(route('roles.update', sharedRole('admin')), ['name' => 'admin', 'permissions' => []])
            ->assertForbidden();
    });
});

describe('names', function () {
    it('lets two businesses each have a role with the same name', function () {
        $this->actingAs($this->fixture->a->owner)->post(route('roles.store'), ['name' => 'supervisor'])->assertSessionHasNoErrors();
        $this->actingAs($this->fixture->b->owner)->post(route('roles.store'), ['name' => 'supervisor'])->assertSessionHasNoErrors();

        expect(Role::withoutGlobalScopes()->where('name', 'supervisor')->pluck('business_id')->sort()->values()->all())
            ->toBe(collect([$this->fixture->a->business->id, $this->fixture->b->business->id])->sort()->values()->all());
    });

    it('refuses a second role by the same name in one business', function () {
        $this->actingAs($this->fixture->a->owner)->post(route('roles.store'), ['name' => 'supervisor'])->assertSessionHasNoErrors();
        $this->actingAs($this->fixture->a->owner)->post(route('roles.store'), ['name' => 'supervisor'])
            ->assertSessionHasErrors(['name' => 'This business already has a role with that name.']);
    });

    it('refuses a built-in role\'s name, on create and on edit alike', function (string $name) {
        $this->actingAs($this->fixture->a->owner)->post(route('roles.store'), ['name' => $name])
            ->assertSessionHasErrors(['name' => 'That name belongs to a built-in role. Choose another name.']);

        $custom = $this->tenant->run($this->fixture->a->business, fn () => Role::create(['name' => 'helper', 'guard_name' => 'web', 'business_id' => $this->fixture->a->business->id]));
        $this->actingAs($this->fixture->a->owner)->put(route('roles.update', $custom), ['name' => $name])
            ->assertSessionHasErrors('name');
        expect($custom->fresh()->name)->toBe('helper');
    })->with(['admin', 'cashier']);

    it('applies one format rule to create and edit', function () {
        $this->actingAs($this->fixture->a->owner)->post(route('roles.store'), ['name' => 'Night Shift'])->assertSessionHasErrors('name');

        $custom = $this->tenant->run($this->fixture->a->business, fn () => Role::create(['name' => 'helper', 'guard_name' => 'web', 'business_id' => $this->fixture->a->business->id]));
        $this->actingAs($this->fixture->a->owner)->put(route('roles.update', $custom), ['name' => 'Night Shift'])->assertSessionHasErrors('name');
    });
});

describe('holders', function () {
    it('counts only the people holding a role in the current business', function () {
        // Both owners hold the shared owner role, each in their own business.
        $roles = $this->actingAs($this->fixture->a->owner)->get(route('roles.index'))->assertOk()->viewData('roles');

        expect($roles->firstWhere('name', 'admin')->holders_count)->toBe(1);
    });

    it('lets a custom role held only in this business be deleted once nobody holds it', function () {
        $custom = $this->tenant->run($this->fixture->a->business, function (): Role {
            $role = Role::create(['name' => 'helper', 'guard_name' => 'web', 'business_id' => $this->fixture->a->business->id]);
            $this->fixture->a->cashier->assignRole($role);

            return $role;
        });

        $this->actingAs($this->fixture->a->owner)->delete(route('roles.destroy', $custom))->assertSessionHas('error');
        expect($custom->fresh())->not->toBeNull();

        $this->tenant->run($this->fixture->a->business, fn () => $this->fixture->a->cashier->removeRole($custom));
        $this->actingAs($this->fixture->a->owner)->delete(route('roles.destroy', $custom))->assertSessionHas('success');
        expect(Role::withoutGlobalScopes()->whereKey($custom->id)->exists())->toBeFalse();
    });
});

describe('the permission cache', function () {
    it('grants each business\'s own roles, whichever business built the cache', function () {
        seedPermissions();
        $b = $this->fixture->b;
        $this->tenant->run($b->business, fn () => Role::query()->where('business_id', $b->business->id)->where('name', 'cashier')->firstOrFail()
            ->givePermissionTo('sales.view'));

        // The shared cache is built while business A is current (as a request
        // of A's would), then read for business B.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->tenant->run($this->fixture->a->business, fn () => Permission::findByName('sales.view'));

        expect($this->tenant->run($b->business, fn () => $b->cashier->fresh()->can('sales.view')))->toBeTrue()
            // ...and A's cashier, whose copy lacks it, still doesn't have it.
            ->and($this->tenant->run($this->fixture->a->business, fn () => $this->fixture->a->cashier->fresh()->can('sales.view')))->toBeFalse();
    });
});

describe('the API role list', function () {
    it('lists the shared roles and the current business\'s own, never another business\'s', function () {
        $custom = $this->tenant->run($this->fixture->b->business, fn () => Role::create(['name' => 'bravo-only', 'guard_name' => 'web', 'business_id' => $this->fixture->b->business->id]));
        $bCashier = Role::withoutGlobalScopes()->where('business_id', $this->fixture->b->business->id)->where('name', 'cashier')->value('id');

        Sanctum::actingAs($this->fixture->a->owner, ['*']);
        $ids = collect($this->getJson(route('api.users.roles'))->assertOk()->json('data'))->pluck('id');

        expect($ids)->not->toContain($custom->id)->not->toContain($bCashier)
            ->and($ids)->toContain(roleOfA($this->fixture, 'cashier')->id)->toContain(sharedRole('admin')->id);
    });
});
