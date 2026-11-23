<?php

use App\Models\Role;
use App\Models\User;

/*
 * Stage 11b, step 4: each role carries the most a person in it may discount a sale without approval (NULL is no limit).
 * The templates are seeded (a cashier, clerk and accountant 0%; a manager and the owner none), and the owner sets a
 * role's own limit on the role screen.
 */

beforeEach(function () {
    seedRolesAndPermissions();
});

it('seeds the templates\' discount limits: the cashier none, the manager and the owner no limit', function () {
    expect((float) sharedRole('cashier')->max_discount_percent)->toBe(0.0)
        ->and((float) sharedRole('inventory-clerk')->max_discount_percent)->toBe(0.0)
        ->and(sharedRole('manager')->max_discount_percent)->toBeNull()
        ->and(sharedRole('admin')->max_discount_percent)->toBeNull();
});

it('lets the owner set a role\'s discount limit on the role screen', function () {
    $owner = User::factory()->create();
    $owner->assignRole(ownerRole());
    $role = Role::create(['name' => 'till-staff', 'guard_name' => 'web']);

    $this->actingAs($owner)->put(route('roles.update', $role), [
        'name' => 'till-staff',
        'max_discount_percent' => 15,
        'permissions' => [],
    ])->assertRedirect();

    expect((float) $role->fresh()->max_discount_percent)->toBe(15.0);
});
