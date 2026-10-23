<?php

use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Support\TwoBusinesses;

/**
 * The business-facing role screens, Phase 5 (A5): the built-in roles by their
 * display names (stored names unchanged, the API still reports them), a
 * permission picker grouped by area that lists only what the editor may
 * give, `full-access` explained where it's chosen, and no "Super Admin".
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->tenant->forget();
    $this->owner = $this->fixture->a->owner;
});

it('names the built-in roles by their display names on every role and people screen', function () {
    $this->actingAs($this->owner)->get(route('roles.index'))->assertOk()
        ->assertSeeText('Owner')->assertSeeText('Inventory clerk')->assertSeeText('Accountant')
        ->assertDontSeeText('inventory-clerk');
    $this->tenant->forget();
    $this->actingAs($this->owner)->get(route('users.index'))->assertOk()->assertSeeText('Owner')->assertSeeText('Cashier');
    $this->tenant->forget();
    $clerkCopy = Role::withoutGlobalScopes()->where('business_id', $this->fixture->a->business->id)->where('name', 'inventory-clerk')->value('id');
    foreach ([route('invites.index')] as $form) {
        $this->tenant->forget();
        expect($this->actingAs($this->owner)->get($form)->assertOk()->getContent())
            ->toMatch('/<option value="'.$clerkCopy.'"[^>]*>\s*Inventory clerk\s*<\/option>/');
    }

    expect(Role::displayNameFor('night-shift'))->toBe('Night shift')
        ->and(Role::displayNameFor('admin'))->toBe('Owner');
});

it('keeps the stored names in the API', function () {
    Sanctum::actingAs($this->owner, ['*']);

    expect($this->getJson('/api/user')->assertOk()->json('data.roles'))->toBe(['admin']);
});

it('says Super Admin on no business screen', function () {
    $copy = Role::withoutGlobalScopes()->where('business_id', $this->fixture->a->business->id)->where('name', 'manager')->firstOrFail();
    $pages = [
        route('roles.index'), route('roles.create'), route('roles.show', $copy), route('roles.edit', $copy),
        route('roles.show', sharedRole('admin')), route('users.index'), route('users.show', $this->owner), route('invites.index'),
    ];

    foreach ($pages as $page) {
        $this->tenant->forget();
        $this->actingAs($this->owner)->get($page)->assertOk()->assertDontSeeText('Super Admin')->assertDontSeeText('super-admin');
    }
});

it('lists only the permissions a non-owner holds, grouped by area, and says so', function () {
    $editor = $this->tenant->run($this->fixture->a->business, function (): User {
        $role = Role::create(['name' => 'role-editor', 'guard_name' => 'web', 'business_id' => $this->fixture->a->business->id]);
        $role->syncPermissions(['roles.view', 'roles.create', 'products.view', 'products.full-access']);
        $user = User::factory()->memberOf($this->fixture->a->business)->create();
        $user->assignRole($role);

        return $user;
    });
    $this->tenant->forget();

    $html = $this->actingAs($editor)->get(route('roles.create'))->assertOk()
        ->assertSeeText('Only the permissions you hold are listed')
        ->assertSeeText('Counts as every permission in this area.')
        ->getContent();

    $id = fn (string $name): int => Permission::findByName($name)->id;
    expect($html)->toContain('value="'.$id('products.view').'"')
        ->toContain('value="'.$id('products.full-access').'"')
        ->not->toContain('value="'.$id('products.delete').'"')
        ->not->toContain('value="'.$id('sales.void').'"');
});

it('lists every permission for an owner, and notes the ones out of a non-owner\'s reach on edit', function () {
    $html = $this->actingAs($this->owner)->get(route('roles.create'))->assertOk()->assertDontSeeText('Only the permissions you hold are listed')->getContent();
    expect(substr_count($html, 'name="permissions[]"'))->toBe(Permission::count());

    $editor = $this->tenant->run($this->fixture->a->business, function (): User {
        $role = Role::create(['name' => 'role-editor', 'guard_name' => 'web', 'business_id' => $this->fixture->a->business->id]);
        $role->syncPermissions(['roles.view', 'roles.update', 'products.view']);
        $user = User::factory()->memberOf($this->fixture->a->business)->create();
        $user->assignRole($role);

        return $user;
    });
    $custom = $this->tenant->run($this->fixture->a->business, function (): Role {
        $role = Role::create(['name' => 'supervisor', 'guard_name' => 'web', 'business_id' => $this->fixture->a->business->id]);
        $role->syncPermissions(['products.view', 'sales.void', 'sales.refund']);

        return $role;
    });
    $this->tenant->forget();

    $this->actingAs($editor)->get(route('roles.edit', $custom))->assertOk()
        ->assertSeeText('This role has 2 more permissions that you don\'t hold. They stay as they are.');
});

it('sends the old permissions page to the edit form', function () {
    $copy = Role::withoutGlobalScopes()->where('business_id', $this->fixture->a->business->id)->where('name', 'manager')->firstOrFail();

    $this->actingAs($this->owner)->get(route('roles.permissions', $copy))->assertRedirect(route('roles.edit', $copy));
});

it('shows no field that saves nothing', function () {
    $copy = Role::withoutGlobalScopes()->where('business_id', $this->fixture->a->business->id)->where('name', 'manager')->firstOrFail();

    foreach ([route('roles.create'), route('roles.edit', $copy)] as $page) {
        $this->tenant->forget();
        $this->actingAs($this->owner)->get($page)->assertOk()
            ->assertDontSee('name="display_name"', false)->assertDontSee('name="description"', false);
    }
});

it('carries the honeypot on the role forms and refuses a request that fills it in', function () {
    $this->actingAs($this->owner)->get(route('roles.create'))->assertOk()->assertSee('name="valid_from"', false);

    $this->tenant->forget();
    $this->actingAs($this->owner)->post(route('roles.store'), ['name' => 'bot-made', 'my_name' => 'I am a bot']);

    expect(Role::withoutGlobalScopes()->where('name', 'bot-made')->exists())->toBeFalse();
});
