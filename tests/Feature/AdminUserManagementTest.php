<?php

use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/**
 * An admin manages the users of their own business through the web screens:
 * the list shows the buttons, and add / view / edit / delete all work. The
 * buttons used to check route names ("users.edit") instead of permissions, so
 * only a super-admin ever saw them.
 */
beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->business = Business::factory()->create();
    $this->admin = User::factory()->create(['business_id' => $this->business->id]);
    $this->admin->assignRole(Role::ADMIN);
    $this->business->update(['owner_id' => $this->admin->id]);

    $this->shop = Shop::factory()->create(['business_id' => $this->business->id, 'manager_id' => $this->admin->id]);
    $this->cashierRole = Role::where('business_id', $this->business->id)->where('name', 'cashier')->sole();

    $this->staff = User::factory()->create(['business_id' => $this->business->id, 'name' => 'Existing Cashier']);
    $this->staff->assignRole('cashier');
    $this->staff->shops()->attach($this->shop);
});

it('shows an admin the view, edit and delete buttons for their staff', function () {
    $this->actingAs($this->admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertSee(route('users.show', $this->staff), false)
        ->assertSee(route('users.edit', $this->staff), false)
        ->assertSee('action="'.route('users.destroy', $this->staff).'"', false)
        ->assertSee(route('users.create'), false)
        ->assertSee(UserStatus::ACTIVE->label());
});

it('lets an admin add a user to their business and shop', function () {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'New Cashier',
            'email' => 'new-cashier@example.test',
            'password' => 'Secret-Pass-123!',
            'password_confirmation' => 'Secret-Pass-123!',
            'status' => UserStatus::ACTIVE->value,
            'role_id' => $this->cashierRole->id,
            'shop_ids' => [$this->shop->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user = User::where('email', 'new-cashier@example.test')->sole();

    expect($user->business_id)->toBe($this->business->id)
        ->and($user->roles->sole()->is($this->cashierRole))->toBeTrue()
        ->and($user->assignedShopIds()->all())->toBe([$this->shop->id]);
});

it('lets an admin view and edit a user, with the actions on the user page', function () {
    $this->actingAs($this->admin);

    $this->get(route('users.show', $this->staff))
        ->assertOk()
        ->assertSee(route('users.edit', $this->staff), false)
        ->assertSee('action="'.route('users.destroy', $this->staff).'"', false);

    $this->get(route('users.edit', $this->staff))->assertOk();

    $this->put(route('users.update', $this->staff), [
        'name' => 'Renamed Cashier',
        'email' => $this->staff->email,
        'status' => UserStatus::ACTIVE->value,
        'role_id' => $this->cashierRole->id,
        'shop_ids' => [$this->shop->id],
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($this->staff->fresh()->name)->toBe('Renamed Cashier');
});

it('lets an admin delete a user', function () {
    $this->actingAs($this->admin)
        ->delete(route('users.destroy', $this->staff))
        ->assertRedirect(route('users.index'));

    expect(User::find($this->staff->id))->toBeNull();
});

it('hides the edit and delete buttons where the admin may not use them', function () {
    $coAdmin = User::factory()->create(['business_id' => $this->business->id]);
    $coAdmin->assignRole(Role::ADMIN);

    $response = $this->actingAs($coAdmin)->get(route('users.index'))->assertOk();

    // The business owner is protected from co-admins, and no one deletes themselves
    $response->assertSee(route('users.edit', $this->staff), false)
        ->assertDontSee(route('users.edit', $this->admin), false)
        ->assertDontSee('action="'.route('users.destroy', $coAdmin).'"', false)
        ->assertDontSee('action="'.route('users.destroy', $this->admin).'"', false);
});

it('shows an admin the inventory and attributes menus', function () {
    $this->actingAs($this->admin)
        ->get(route('users.index'))
        ->assertSee(route('stock-intakes.index'), false)
        ->assertSee(route('attributes.index'), false);
});
