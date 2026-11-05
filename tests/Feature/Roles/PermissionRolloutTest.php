<?php

use App\Actions\Platform\UpdateRoleTemplate;
use App\Actions\Role\ResetRoleToTemplate;
use App\Actions\Role\SyncRolePermissions;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Support\Authorization\RolloutBackfill;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/*
 * Permissions reach every business, whenever it was created, and what a
 * business or the platform console takes off a role stays off (stage 10,
 * step 0). The rollout used to decide what was new by comparing timestamps,
 * which missed a permission created before a business's copies but granted
 * to the template after; removals are now recorded in role_revoked_permissions.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->tenant = app(TenantContext::class);
});

function rolloutTemplate(string $name): Role
{
    return Role::withoutGlobalScopes()->whereNull('business_id')->where('name', $name)->firstOrFail();
}

function rolloutCopy(Business $business, string $name): Role
{
    return Role::withoutGlobalScopes()->where('business_id', $business->id)->where('name', $name)->firstOrFail();
}

/**
 * @return list<string>
 */
function rolloutPermissionNames(Role $role): array
{
    return $role->permissions()->pluck('name')->sort()->values()->all();
}

function rolloutSeed(): void
{
    test()->seed(RoleSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('gives a business\'s copy a permission created before the business but granted to the template after (the race)', function () {
    $permission = Permission::create(['name' => 'demo.report', 'guard_name' => 'web']);
    $business = Business::factory()->create();
    rolloutTemplate('manager')->givePermissionTo($permission);

    rolloutSeed();

    expect(rolloutPermissionNames(rolloutCopy($business, 'manager')))->toContain('demo.report');
});

it('keeps a permission a business took off its copy off when permissions roll out again', function () {
    $business = Business::factory()->create();
    $owner = $this->tenant->run($business, function () use ($business): User {
        $owner = User::factory()->memberOf($business)->create();
        $owner->assignRole('admin');

        return $owner;
    });
    $copy = rolloutCopy($business, 'manager');
    $keep = $copy->permissions()->where('name', '!=', 'products.view')->pluck('permissions.id')->all();

    $this->tenant->run($business, fn () => app(SyncRolePermissions::class)->execute($copy, $owner, $keep));
    rolloutSeed();

    expect(rolloutPermissionNames($copy->fresh()))->not->toContain('products.view')
        ->and(DB::table('role_revoked_permissions')->where('role_id', $copy->id)->value('revoked_by'))->toBe($owner->id);

    // Given back on the screen, it is no longer recorded as removed.
    $this->tenant->run($business, fn () => app(SyncRolePermissions::class)->execute($copy, $owner, [...$keep, Permission::findByName('products.view')->id]));
    expect(DB::table('role_revoked_permissions')->where('role_id', $copy->id)->exists())->toBeFalse();
});

it('keeps a permission the platform console took off a template off', function () {
    $template = rolloutTemplate('cashier');
    $keep = $template->permissions()->where('name', '!=', 'sales.refund')->pluck('permissions.id')->all();
    $this->actingAs(platformOperator());

    app(UpdateRoleTemplate::class)->execute($template, $keep);
    rolloutSeed();

    expect(rolloutPermissionNames($template->fresh()))->not->toContain('sales.refund');
});

it('gives back what the template holds on "Reset to default", and the rollout keeps it', function () {
    $business = Business::factory()->create();
    $owner = $this->tenant->run($business, function () use ($business): User {
        $owner = User::factory()->memberOf($business)->create();
        $owner->assignRole('admin');

        return $owner;
    });
    $copy = rolloutCopy($business, 'cashier');
    $this->tenant->run($business, fn () => app(SyncRolePermissions::class)->execute($copy, $owner, []));

    app(ResetRoleToTemplate::class)->execute($copy->fresh());
    rolloutSeed();

    expect(rolloutPermissionNames($copy->fresh()))->toBe(rolloutPermissionNames(rolloutTemplate('cashier')))
        ->and(DB::table('role_revoked_permissions')->where('role_id', $copy->id)->exists())->toBeFalse();
});

it('backfills: grants the permissions the old rollout missed, records other absences as revoked, and is idempotent', function () {
    $business = Business::factory()->create();
    $copy = rolloutCopy($business, 'manager');
    $missed = Permission::findByName('registers.view');
    $byHand = Permission::findByName('products.view');
    DB::table('role_has_permissions')->where('role_id', $copy->id)->whereIn('permission_id', [$missed->id, $byHand->id])->delete();

    $this->artisan('roles:backfill-rollout', ['--business' => $business->id])->assertSuccessful();
    expect(rolloutPermissionNames($copy->fresh()))->not->toContain('registers.view');

    $this->artisan('roles:backfill-rollout', ['--business' => $business->id, '--apply' => true])->assertSuccessful();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(rolloutPermissionNames($copy->fresh()))->toContain('registers.view')->not->toContain('products.view')
        ->and(DB::table('role_revoked_permissions')->where('role_id', $copy->id)->pluck('permission_id')->all())->toBe([$byHand->id])
        ->and(app(RolloutBackfill::class)->run($business->id, apply: true))->toBe([]);
});

it('changes nothing when permissions:sync runs a second time', function () {
    Business::factory()->create();
    $this->artisan('permissions:sync')->assertSuccessful();
    $grants = DB::table('role_has_permissions')->count();
    $permissions = DB::table('permissions')->count();

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(DB::table('role_has_permissions')->count())->toBe($grants)
        ->and(DB::table('permissions')->count())->toBe($permissions);
});

it('in the real deploy order, keeps a removal made by hand before this stage and gives the manager copy the new permissions', function () {
    // Before this stage: the new permissions don't exist, and business A took products.view off its manager copy by hand.
    $business = Business::factory()->create();
    $copy = rolloutCopy($business, 'manager');
    DB::table('permissions')->whereIn('name', RolloutBackfill::NEW)->delete();
    DB::table('role_has_permissions')->where('role_id', $copy->id)
        ->where('permission_id', Permission::findByName('products.view')->id)->delete();
    DB::table('role_revoked_permissions')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // The migration's backfill, then permissions:sync.
    DB::transaction(fn () => app(RolloutBackfill::class)->run(null, apply: true));
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(rolloutPermissionNames($copy->fresh()))->not->toContain('products.view')
        ->toContain('cash-registers.payout')->toContain('sync-issues.accept')->toContain('sync-issues.discard');
});

it('lets only platform operators through Gate::before, never a business owner', function () {
    $owner = User::factory()->create();
    $owner->assignRole('admin');

    expect(Gate::forUser($owner)->allows('an-ability-nobody-defines'))->toBeFalse()
        ->and(Gate::forUser(platformOperator())->allows('an-ability-nobody-defines'))->toBeTrue();
});
