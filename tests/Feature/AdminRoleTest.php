<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole(Role::SUPER_ADMIN);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::ADMIN);
});

/**
 * @return array<string, mixed>
 */
function newUserPayload(array $roles): array
{
    return [
        'name' => 'New Staff',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'Secret-Pass-123!',
        'roles' => $roles,
    ];
}

test('the admin role is seeded with every permission', function () {
    $admin = Role::findByName(Role::ADMIN);

    expect($admin->permissions()->count())->toBe(Permission::count())
        ->and(Role::findByName(Role::SUPER_ADMIN)->permissions()->count())->toBe(Permission::count());
});

test('admin is a regular permission holder, not a platform super-admin', function () {
    expect($this->admin->isSuperAdmin())->toBeFalse()
        ->and($this->superAdmin->isSuperAdmin())->toBeTrue();
});

test('admin can create staff with ordinary roles', function () {
    $this->actingAs($this->admin)
        ->postJson(route('api.users.store'), newUserPayload(['cashier']))
        ->assertCreated();
});

test('admin cannot grant the super-admin role over the API', function () {
    $this->actingAs($this->admin)
        ->postJson(route('api.users.store'), newUserPayload([Role::SUPER_ADMIN]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('roles.0');

    expect(User::role(Role::SUPER_ADMIN)->count())->toBe(1);
});

test('admin cannot grant the super-admin role through the web form', function () {
    $superAdminRole = Role::findByName(Role::SUPER_ADMIN);

    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.test',
            'password' => 'Secret-Pass-123!',
            'password_confirmation' => 'Secret-Pass-123!',
            'status' => 'active',
            'role_id' => $superAdminRole->id,
        ])
        ->assertSessionHasErrors('roles.0');

    expect(User::where('email', 'sneaky@example.test')->exists())->toBeFalse();
});

test('admin cannot promote an existing user to super-admin', function () {
    $staff = User::factory()->create();

    $this->actingAs($this->admin)
        ->putJson(route('api.users.update', $staff), ['roles' => [Role::SUPER_ADMIN]])
        ->assertUnprocessable();

    expect($staff->fresh()->isSuperAdmin())->toBeFalse();
});

test('super-admin can grant the super-admin role', function () {
    $this->actingAs($this->superAdmin)
        ->postJson(route('api.users.store'), newUserPayload([Role::SUPER_ADMIN]))
        ->assertCreated();

    expect(User::role(Role::SUPER_ADMIN)->count())->toBe(2);
});

test('admin cannot view, edit or delete a super-admin account', function () {
    $this->actingAs($this->admin);

    $this->getJson(route('api.users.show', $this->superAdmin))->assertForbidden();
    $this->putJson(route('api.users.update', $this->superAdmin), ['name' => 'Hijacked'])->assertForbidden();
    $this->deleteJson(route('api.users.destroy', $this->superAdmin))->assertForbidden();
    $this->get(route('users.edit', $this->superAdmin))->assertForbidden();

    expect($this->superAdmin->fresh()->name)->not->toBe('Hijacked');
});

test('super-admins are hidden from the admin user list and role picker', function () {
    $this->actingAs($this->admin);

    $listed = collect($this->getJson(route('api.users.index'))->assertOk()->json('data.data'))->pluck('id');
    $roles = collect($this->getJson(route('api.users.roles'))->assertOk()->json('data'))->pluck('name');

    expect($listed)->toContain($this->admin->id)
        ->not->toContain($this->superAdmin->id)
        ->and($roles)->toContain(Role::ADMIN)
        ->not->toContain(Role::SUPER_ADMIN);
});

test('super-admin sees super-admins in the user list', function () {
    $listed = collect($this->actingAs($this->superAdmin)->getJson(route('api.users.index'))->json('data.data'))->pluck('id');

    expect($listed)->toContain($this->superAdmin->id);
});

test('admin cannot modify or delete the system roles', function () {
    $this->actingAs($this->admin);

    foreach ([Role::ADMIN, Role::SUPER_ADMIN] as $name) {
        $role = Role::findByName($name);

        $this->put(route('roles.update', $role), ['name' => $name, 'permissions' => []])->assertForbidden();
        $this->put(route('roles.updatePermissions', $role), ['permissions' => []])->assertForbidden();
        $this->delete(route('roles.destroy', $role))->assertForbidden();
    }

    expect(Role::findByName(Role::ADMIN)->permissions()->count())->toBe(Permission::count());
});

test('admin can still manage custom roles', function () {
    $role = Role::create(['name' => 'stock-taker', 'business_id' => $this->admin->business_id]);

    $this->actingAs($this->admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    expect(Role::where('name', 'stock-taker')->exists())->toBeFalse();
});

test('super-admin cannot rename or delete the admin role', function () {
    $role = Role::findByName(Role::ADMIN);

    $this->actingAs($this->superAdmin)
        ->put(route('roles.update', $role), ['name' => 'owner', 'permissions' => []])
        ->assertSessionHas('error');

    $this->actingAs($this->superAdmin)
        ->delete(route('roles.destroy', $role))
        ->assertSessionHas('error');

    expect(Role::where('name', Role::ADMIN)->exists())->toBeTrue()
        ->and($role->fresh()->permissions()->count())->toBe(Permission::count());
});

test('users without users.create cannot create users over the API', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    $this->actingAs($cashier)
        ->postJson(route('api.users.store'), newUserPayload([Role::SUPER_ADMIN]))
        ->assertForbidden();
});
