<?php

use App\Models\Role;
use App\Models\User;
use Database\Factories\BusinessFactory;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Create permissions for testing
    Permission::create(['name' => 'roles.view']);
    Permission::create(['name' => 'roles.create']);
    Permission::create(['name' => 'roles.update']);
    Permission::create(['name' => 'roles.delete']);
});

test('user can view roles index if authorized', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo('roles.view');

    $response = $this->actingAs($user)->get(route('roles.index'));

    $response->assertStatus(200);
});

test('user cannot view roles index if not authorized', function () {
    $user = User::factory()->owner()->create();

    $response = $this->actingAs($user)->get(route('roles.index'));

    $response->assertStatus(403);
});

test('user can create role if authorized', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['roles.create', 'roles.view']);

    $response = $this->actingAs($user)->post(route('roles.store'), [
        'name' => 'test-role',
        'permissions' => [],
    ]);

    $response->assertRedirect(route('roles.index'));
    $this->assertDatabaseHas('roles', ['name' => 'test-role']);
});

test('user cannot create role if not authorized', function () {
    $user = User::factory()->owner()->create();

    $response = $this->actingAs($user)->post(route('roles.store'), [
        'name' => 'test-role',
        'permissions' => [],
    ]);

    $response->assertStatus(403);
});

test('role name must be unique', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['roles.create', 'roles.view']);
    Role::create(['name' => 'existing-role']);

    $response = $this->actingAs($user)->post(route('roles.store'), [
        'name' => 'existing-role',
        'permissions' => [],
    ]);

    $response->assertSessionHasErrors('name');
});

test('role name must contain only lowercase letters numbers and hyphens', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['roles.create', 'roles.view']);

    $response = $this->actingAs($user)->post(route('roles.store'), [
        'name' => 'Invalid Role Name',
        'permissions' => [],
    ]);

    // Should return validation errors
    $response->assertStatus(302); // Redirect back with errors
    $response->assertSessionHasErrors('name');
});

test('user can update role if authorized', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['roles.update', 'roles.view']);
    $role = Role::create(['name' => 'old-role', 'business_id' => BusinessFactory::defaultId()]);

    $response = $this->actingAs($user)->put(route('roles.update', $role), [
        'name' => 'updated-role',
        'permissions' => [],
    ]);

    $response->assertRedirect(route('roles.index'));
    $this->assertDatabaseHas('roles', ['name' => 'updated-role']);
});

test('user cannot update role if not authorized', function () {
    $user = User::factory()->owner()->create();
    $role = Role::create(['name' => 'test-role', 'business_id' => BusinessFactory::defaultId()]);

    $response = $this->actingAs($user)->put(route('roles.update', $role), [
        'name' => 'updated-role',
        'permissions' => [],
    ]);

    $response->assertStatus(403);
});

test('super-admin role cannot be updated', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['roles.update', 'roles.view']);
    $superAdminRole = Role::create(['name' => 'super-admin']);

    $response = $this->actingAs($user)->put(route('roles.update', $superAdminRole), [
        'name' => 'hacked-role',
        'permissions' => [],
    ]);

    // RolePolicy rejects any change to a system role by a non-super-admin
    $response->assertStatus(403);
    $this->assertDatabaseHas('roles', ['name' => 'super-admin']);
});

test('user can delete role if authorized', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['roles.delete', 'roles.view']);
    $role = Role::create(['name' => 'deletable-role', 'business_id' => BusinessFactory::defaultId()]);

    $response = $this->actingAs($user)->delete(route('roles.destroy', $role));

    $response->assertRedirect(route('roles.index'));
    $this->assertDatabaseMissing('roles', ['name' => 'deletable-role']);
});

test('user cannot delete role if not authorized', function () {
    $user = User::factory()->owner()->create();
    $role = Role::create(['name' => 'test-role', 'business_id' => BusinessFactory::defaultId()]);

    $response = $this->actingAs($user)->delete(route('roles.destroy', $role));

    $response->assertStatus(403);
});

test('super-admin role cannot be deleted', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['roles.delete', 'roles.view']);
    $superAdminRole = Role::create(['name' => 'super-admin']);

    $response = $this->actingAs($user)->delete(route('roles.destroy', $superAdminRole));

    // Policy returns 403 Forbidden for super-admin role
    $response->assertStatus(403);
    $this->assertDatabaseHas('roles', ['name' => 'super-admin']);
});

test('role with assigned users cannot be deleted', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['roles.delete', 'roles.view']);
    $role = Role::create(['name' => 'assigned-role', 'business_id' => BusinessFactory::defaultId()]);

    $assignedUser = User::factory()->owner()->create();
    $assignedUser->assignRole($role);

    $response = $this->actingAs($user)->delete(route('roles.destroy', $role));

    $response->assertRedirect(route('roles.index'));
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('roles', ['name' => 'assigned-role']);
});

test('permissions can be assigned to role', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['roles.create', 'roles.view']);

    $permission1 = Permission::create(['name' => 'test.permission1']);
    $permission2 = Permission::create(['name' => 'test.permission2']);

    $response = $this->actingAs($user)->post(route('roles.store'), [
        'name' => 'test-role-with-perms',
        'permissions' => [$permission1->id, $permission2->id],
    ]);

    $role = Role::where('name', 'test-role-with-perms')->first();

    expect($role->permissions)->toHaveCount(2);
    expect($role->hasPermissionTo('test.permission1'))->toBeTrue();
    expect($role->hasPermissionTo('test.permission2'))->toBeTrue();
});

test('permissions can be updated for role', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['roles.update', 'roles.view']);

    $role = Role::create(['name' => 'test-role', 'business_id' => BusinessFactory::defaultId()]);
    $permission1 = Permission::create(['name' => 'test.permission1']);
    $permission2 = Permission::create(['name' => 'test.permission2']);
    $permission3 = Permission::create(['name' => 'test.permission3']);

    $role->givePermissionTo($permission1);

    $response = $this->actingAs($user)->put(route('roles.updatePermissions', $role), [
        'permissions' => [$permission2->id, $permission3->id],
    ]);

    $role->refresh();

    expect($role->permissions)->toHaveCount(2);
    expect($role->hasPermissionTo('test.permission1'))->toBeFalse();
    expect($role->hasPermissionTo('test.permission2'))->toBeTrue();
    expect($role->hasPermissionTo('test.permission3'))->toBeTrue();
});

test('super-admin role permissions cannot be modified', function () {
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['roles.update', 'roles.view']);

    $superAdminRole = Role::create(['name' => 'super-admin']);
    $allPermissions = Permission::all()->pluck('id')->toArray();
    $superAdminRole->givePermissionTo(Permission::all());

    $permission = Permission::create(['name' => 'test.permission']);
    $countBefore = $superAdminRole->fresh()->permissions()->count();

    $response = $this->actingAs($user)->put(route('roles.updatePermissions', $superAdminRole), [
        'permissions' => [$permission->id],
    ]);

    // RolePolicy rejects any change to a system role by a non-super-admin; had
    // it gone through, the role would be left with only test.permission
    $response->assertStatus(403);
    expect($superAdminRole->fresh()->permissions()->count())->toBe($countBefore);
});
