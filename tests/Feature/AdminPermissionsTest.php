<?php

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\DefaultRoles;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

/**
 * An admin holds every permission a super-admin holds, but only over their own
 * business: roles are per business, the data-integrity rules still bind them,
 * new permissions reach them automatically, and the business owner cannot be
 * demoted by a co-admin.
 */
beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    $this->alpha = Business::factory()->create(['name' => 'Alpha Ltd']);
    $this->bravo = Business::factory()->create(['name' => 'Bravo Ltd']);

    $this->owner = User::factory()->create(['business_id' => $this->alpha->id]);
    $this->owner->assignRole(Role::ADMIN);
    $this->alpha->update(['owner_id' => $this->owner->id]);

    $this->bravoOwner = User::factory()->create(['business_id' => $this->bravo->id]);
    $this->bravoOwner->assignRole(Role::ADMIN);
    $this->bravo->update(['owner_id' => $this->bravoOwner->id]);

    $this->alphaShop = Shop::factory()->create(['business_id' => $this->alpha->id]);

    $this->superAdmin = User::factory()->create(['business_id' => null]);
    $this->superAdmin->assignRole(Role::SUPER_ADMIN);
});

function businessRole(Business $business, string $name): Role
{
    return Role::query()->where('business_id', $business->id)->where('name', $name)->sole();
}

describe('permissions', function () {
    it('gives an admin every permission a super-admin has', function () {
        expect($this->owner->getAllPermissions()->pluck('name')->sort()->values()->all())
            ->toBe(Permission::query()->pluck('name')->sort()->values()->all());
    });

    it('grants a newly created permission to admins straight away', function () {
        Permission::create(['name' => 'loyalty.manage']);

        expect($this->owner->fresh()->hasPermissionTo('loyalty.manage'))->toBeTrue()
            ->and(Role::global(Role::SUPER_ADMIN)->hasPermissionTo('loyalty.manage'))->toBeTrue();
    });

    it('still holds an admin to the data-integrity rules a super-admin bypasses', function () {
        $movement = StockMovement::factory()->make(['shop_id' => $this->alphaShop->id]);

        expect(Gate::forUser($this->owner)->allows('update', $movement))->toBeFalse()
            ->and(Gate::forUser($this->superAdmin)->allows('update', $movement))->toBeTrue();
    });
});

describe('rules that full access does not override', function () {
    it('holds an admin to the purchase-order workflow', function () {
        $draft = PurchaseOrder::factory()->draft()->create(['shop_id' => $this->alphaShop->id]);
        $pending = PurchaseOrder::factory()->pending()->create(['shop_id' => $this->alphaShop->id]);

        // The admin holds purchase_orders.full-access, which used to approve anything
        expect($this->owner->can('purchase_orders.full-access'))->toBeTrue()
            ->and(Gate::forUser($this->owner)->allows('approve', $pending))->toBeTrue()
            ->and(Gate::forUser($this->owner)->allows('approve', $draft))->toBeFalse();
    });

    it('shows an admin only their own business\'s shop-less audit entries', function () {
        $ownEntry = (new AuditLog)->forceFill(['shop_id' => null, 'user_id' => $this->owner->id]);
        $foreignEntry = (new AuditLog)->forceFill(['shop_id' => null, 'user_id' => $this->bravoOwner->id]);

        expect(Gate::forUser($this->owner)->allows('view', $ownEntry))->toBeTrue()
            ->and(Gate::forUser($this->owner)->allows('view', $foreignEntry))->toBeFalse()
            ->and(Gate::forUser($this->superAdmin)->allows('view', $foreignEntry))->toBeTrue();
    });
});

describe('per-business roles', function () {
    it('lists only the admin\'s own users on a shared role\'s page', function () {
        $this->actingAs($this->owner)
            ->get(route('roles.show', Role::global(Role::ADMIN)))
            ->assertOk()
            ->assertSee($this->owner->email)
            ->assertDontSee($this->bravoOwner->email);
    });

    it('gives every business its own copy of the default roles', function () {
        foreach ([$this->alpha, $this->bravo] as $business) {
            expect(Role::where('business_id', $business->id)->pluck('name')->sort()->values()->all())
                ->toBe(collect(DefaultRoles::definitions())->keys()->sort()->values()->all());
        }

        expect(businessRole($this->alpha, 'cashier')->permissions()->count())->toBeGreaterThan(0);
    });

    it('keeps one business\'s role changes away from every other business', function () {
        $alphaManager = businessRole($this->alpha, 'manager');
        $bravoManager = businessRole($this->bravo, 'manager');
        $before = $bravoManager->permissions()->count();

        $this->actingAs($this->owner)
            ->put(route('roles.updatePermissions', $alphaManager), [
                'permissions' => Permission::where('name', 'sales.view')->pluck('id')->all(),
            ])
            ->assertRedirect(route('roles.index'));

        expect($alphaManager->fresh()->permissions()->pluck('name')->all())->toBe(['sales.view'])
            ->and($bravoManager->fresh()->permissions()->count())->toBe($before);
    });

    it('applies a role change to that business\'s staff only', function () {
        $alphaStaff = User::factory()->create(['business_id' => $this->alpha->id]);
        $alphaStaff->assignRole('manager');
        $bravoStaff = User::factory()->create(['business_id' => $this->bravo->id]);
        $bravoStaff->assignRole('manager');

        expect($alphaStaff->roles->sole()->business_id)->toBe($this->alpha->id)
            ->and($bravoStaff->roles->sole()->business_id)->toBe($this->bravo->id);

        businessRole($this->alpha, 'manager')->revokePermissionTo('reports.view');

        expect($alphaStaff->fresh()->hasPermissionTo('reports.view'))->toBeFalse()
            ->and($bravoStaff->fresh()->hasPermissionTo('reports.view'))->toBeTrue();
    });

    it('lists only the system roles and the admin\'s own roles', function () {
        $listed = $this->actingAs($this->owner)->get(route('roles.index'))
            ->assertOk()
            ->viewData('roles')
            ->getCollection();

        expect($listed->every(fn (Role $role) => $role->business_id === null || $role->business_id === $this->alpha->id))->toBeTrue()
            ->and($listed->pluck('id'))->toContain(businessRole($this->alpha, 'cashier')->id)
            ->not->toContain(businessRole($this->bravo, 'cashier')->id);
    });

    it('keeps another business\'s roles out of reach', function () {
        $bravoCashier = businessRole($this->bravo, 'cashier');

        $this->actingAs($this->owner);

        $this->get(route('roles.show', $bravoCashier))->assertForbidden();
        $this->put(route('roles.update', $bravoCashier), ['name' => 'hijacked'])->assertForbidden();
        $this->delete(route('roles.destroy', $bravoCashier))->assertForbidden();

        expect($bravoCashier->fresh()->name)->toBe('cashier');
    });

    it('lets an admin read but not change the shared system roles', function () {
        $admin = Role::global(Role::ADMIN);

        $this->actingAs($this->owner);

        $this->get(route('roles.show', $admin))->assertOk();
        $this->put(route('roles.updatePermissions', $admin), ['permissions' => []])->assertForbidden();

        expect($admin->fresh()->permissions()->count())->toBe(Permission::count());
    });

    it('creates an admin\'s new role inside their business', function () {
        $this->actingAs($this->owner)
            ->post(route('roles.store'), ['name' => 'stock-taker', 'permissions' => []])
            ->assertRedirect(route('roles.index'));

        $this->actingAs($this->bravoOwner)
            ->post(route('roles.store'), ['name' => 'stock-taker', 'permissions' => []])
            ->assertRedirect(route('roles.index'));

        expect(Role::where('name', 'stock-taker')->pluck('business_id')->sort()->values()->all())
            ->toBe([$this->alpha->id, $this->bravo->id]);
    });

    it('refuses a role name already used by a system role or in the business', function (string $name) {
        $this->actingAs($this->owner)
            ->from(route('roles.create'))
            ->post(route('roles.store'), ['name' => $name, 'permissions' => []])
            ->assertSessionHasErrors('name');
    })->with(['admin', 'super-admin', 'cashier']);
});

describe('co-admins and the business owner', function () {
    beforeEach(function () {
        $this->coAdmin = User::factory()->create(['business_id' => $this->alpha->id]);
        $this->coAdmin->assignRole(Role::ADMIN);
        $this->coAdmin->shops()->attach($this->alphaShop);
    });

    it('lets an admin make another member of staff an admin', function () {
        $this->actingAs($this->owner)
            ->postJson('/api/users', [
                'name' => 'Second Admin',
                'email' => 'second-admin@example.test',
                'password' => 'Secret-Pass-123!',
                'roles' => [Role::ADMIN],
            ])
            ->assertCreated();

        $secondAdmin = User::where('email', 'second-admin@example.test')->sole();

        expect($secondAdmin->isAdmin())->toBeTrue()
            ->and($secondAdmin->business_id)->toBe($this->alpha->id);
    });

    it('stops a co-admin from demoting, suspending or deleting the owner', function () {
        $this->actingAs($this->coAdmin);

        $this->putJson("/api/users/{$this->owner->uuid}", ['roles' => ['cashier']])->assertForbidden();
        $this->post(route('users.suspend', $this->owner))->assertForbidden();
        $this->deleteJson("/api/users/{$this->owner->uuid}")->assertForbidden();

        expect($this->owner->fresh()->isAdmin())->toBeTrue()
            ->and($this->owner->fresh()->status)->toBe(UserStatus::ACTIVE);
    });

    it('lets the owner manage a co-admin', function () {
        $this->actingAs($this->owner)
            ->putJson("/api/users/{$this->coAdmin->uuid}", ['roles' => ['manager']])
            ->assertOk();

        expect($this->coAdmin->fresh()->isAdmin())->toBeFalse();
    });

    it('stops the owner from removing their own admin role or deactivating themselves', function () {
        $this->actingAs($this->owner);

        $this->putJson("/api/users/{$this->owner->uuid}", ['roles' => ['manager']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('roles');

        $this->putJson("/api/users/{$this->owner->uuid}", ['status' => UserStatus::INACTIVE->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        // Editing their own details is fine
        $this->putJson("/api/users/{$this->owner->uuid}", ['name' => 'Renamed Owner'])->assertOk();

        expect($this->owner->fresh()->isAdmin())->toBeTrue();
    });

    it('stops a co-admin deleting the owner, on the web and over the API', function () {
        $this->actingAs($this->coAdmin)
            ->delete(route('users.destroy', $this->owner))
            ->assertForbidden();

        $this->actingAs($this->coAdmin)
            ->deleteJson("/api/users/{$this->owner->uuid}")
            ->assertForbidden();

        expect(User::find($this->owner->id))->not->toBeNull();
    });

    it('lets the owner delete a co-admin', function () {
        $this->actingAs($this->owner)
            ->delete(route('users.destroy', $this->coAdmin))
            ->assertRedirect(route('users.index'));

        expect(User::find($this->coAdmin->id))->toBeNull();
    });

    it('stops a co-admin deleting anyone, themselves or another co-admin', function () {
        $otherCoAdmin = User::factory()->create(['business_id' => $this->alpha->id]);
        $otherCoAdmin->assignRole(Role::ADMIN);

        // Only the business owner deletes accounts
        $this->actingAs($this->coAdmin)->delete(route('users.destroy', $this->coAdmin))->assertForbidden();
        $this->actingAs($this->coAdmin)->delete(route('users.destroy', $otherCoAdmin))->assertForbidden();

        expect(User::find($this->coAdmin->id))->not->toBeNull()
            ->and(User::find($otherCoAdmin->id))->not->toBeNull();
    });

    it('stops an admin deleting an admin of another business', function () {
        $this->actingAs($this->owner)
            ->deleteJson("/api/users/{$this->bravoOwner->uuid}")
            ->assertForbidden();

        expect(User::find($this->bravoOwner->id))->not->toBeNull();
    });

    it('stops a super-admin deleting any user, on the web and over the API', function () {
        $this->actingAs($this->superAdmin);

        $this->deleteJson("/api/users/{$this->owner->uuid}")->assertForbidden();
        $this->delete(route('users.destroy', $this->coAdmin))->assertForbidden();

        // and offers no delete button, while edit stays available
        $this->get(route('users.index'))
            ->assertOk()
            ->assertSee(route('users.edit', $this->coAdmin), false)
            ->assertDontSee('action="'.route('users.destroy', $this->coAdmin).'"', false);

        expect(User::find($this->owner->id))->not->toBeNull()
            ->and(User::find($this->coAdmin->id))->not->toBeNull();
    });

    it('still lets a super-admin suspend a user', function () {
        $this->actingAs($this->superAdmin)
            ->post(route('users.suspend', $this->coAdmin))
            ->assertRedirect();

        expect($this->coAdmin->fresh()->status)->toBe(UserStatus::SUSPENDED);
    });

    it('lets a super-admin demote an owner', function () {
        $this->actingAs($this->superAdmin)
            ->putJson("/api/users/{$this->owner->uuid}", ['roles' => ['manager']])
            ->assertOk();

        expect($this->owner->fresh()->isAdmin())->toBeFalse()
            ->and($this->owner->fresh()->roles->sole()->business_id)->toBe($this->alpha->id);
    });
});
