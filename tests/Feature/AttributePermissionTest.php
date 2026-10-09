<?php

use App\Models\Attribute;
use App\Models\Business;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/**
 * The attribute screens had no permission checks: any signed-in user could
 * create, edit or delete attributes. They now need attributes.* permissions,
 * which admins hold and managers and inventory clerks get by default.
 */
beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->business = Business::factory()->create();
    $this->shop = Shop::factory()->create(['business_id' => $this->business->id]);
    $this->attribute = Attribute::factory()->create(['business_id' => $this->business->id, 'name' => 'Size']);
});

function attributeStaff(string $role): User
{
    $user = User::factory()->create(['business_id' => test()->business->id]);
    $user->assignRole($role);
    $user->shops()->attach(test()->shop);

    return $user;
}

it('refuses every attribute screen and action to a cashier', function () {
    $this->actingAs(attributeStaff('cashier'));

    $this->get(route('attributes.index'))->assertForbidden();
    $this->get(route('attributes.create'))->assertForbidden();
    $this->post(route('attributes.store'), ['name' => 'Colour', 'type' => 'dropdown'])->assertForbidden();
    $this->get(route('attributes.show', $this->attribute))->assertForbidden();
    $this->get(route('attributes.edit', $this->attribute))->assertForbidden();
    $this->put(route('attributes.update', $this->attribute), ['name' => 'Hacked', 'type' => 'dropdown'])->assertForbidden();
    $this->post(route('attributes.deactivate', $this->attribute))->assertForbidden();
    $this->delete(route('attributes.destroy', $this->attribute))->assertForbidden();

    expect($this->attribute->fresh()->name)->toBe('Size')
        ->and(Attribute::where('name', 'Colour')->exists())->toBeFalse();
});

it('hides the attributes menu from a cashier', function () {
    $this->actingAs(attributeStaff('cashier'))
        ->get(route('dashboard'))
        ->assertDontSee(route('attributes.index'), false);
});

it('lets the roles that manage products manage attributes', function (string $role) {
    $this->actingAs(attributeStaff($role));

    $this->get(route('attributes.index'))
        ->assertOk()
        ->assertSee(route('attributes.edit', $this->attribute), false);

    $this->post(route('attributes.store'), ['name' => 'Colour', 'type' => 'dropdown'])
        ->assertRedirect(route('attributes.index'));

    $this->put(route('attributes.update', $this->attribute), ['name' => 'Shoe size', 'type' => 'dropdown'])
        ->assertRedirect(route('attributes.index'));

    $this->delete(route('attributes.destroy', $this->attribute))->assertRedirect(route('attributes.index'));

    expect(Attribute::where('name', 'Colour')->exists())->toBeTrue()
        ->and(Attribute::find($this->attribute->id))->toBeNull();
})->with([Role::ADMIN, 'manager', 'inventory-clerk']);

it('keeps another business\'s attributes out of reach', function () {
    $other = Attribute::factory()->create(['business_id' => Business::factory()->create()->id]);

    $this->actingAs(attributeStaff(Role::ADMIN))
        ->get(route('attributes.edit', $other))
        ->assertNotFound();
});
