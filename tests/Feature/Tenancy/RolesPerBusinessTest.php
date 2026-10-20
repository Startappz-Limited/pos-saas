<?php

use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TwoBusinesses;

/**
 * Roles and permissions are held per business (Spatie teams on business_id,
 * stage 1a, decision 4.4).
 */
beforeEach(function () {
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
});

it('holds a role in the business it was given in only', function () {
    $both = $this->fixture->both;
    $this->tenant->run($this->fixture->a->business, fn () => $both->assignRole(sharedRole('manager')));

    $inA = $this->tenant->run($this->fixture->a->business, fn () => $both->fresh()->hasRole('manager'));
    $inB = $this->tenant->run($this->fixture->b->business, fn () => $both->fresh()->hasRole('manager'));

    expect($inA)->toBeTrue()->and($inB)->toBeFalse();
});

it('gives an owner every permission in their own business only (stage 3)', function () {
    $owner = $this->fixture->a->owner;
    $owner->joinBusiness($this->fixture->b->business);
    $this->actingAs($owner);

    $this->tenant->set($this->fixture->a->business);
    $inA = Gate::allows('products.delete');

    $this->tenant->set($this->fixture->b->business);
    $inB = Gate::allows('products.delete');

    // Only the platform operator passes every gate; an owner doesn't.
    expect($inA)->toBeTrue()->and($inB)->toBeFalse()
        ->and(Gate::forUser($owner)->allows('anything.at-all'))->toBeFalse();
});

it('lets only the platform operator pass every gate, in no business (stage 3)', function () {
    $operator = User::factory()->create();
    $operator->forceFill(['is_platform_operator' => true])->save();
    $this->tenant->forget();

    expect(Gate::forUser($operator)->allows('anything.at-all'))->toBeTrue()
        ->and($operator->isBusinessOwner())->toBeFalse();
});

it('grants a permission in one business only', function () {
    $both = $this->fixture->both;
    Permission::findOrCreate('sales.view');
    $this->tenant->run($this->fixture->b->business, fn () => $both->givePermissionTo('sales.view'));

    $inA = $this->tenant->run($this->fixture->a->business, fn () => $both->fresh()->hasPermissionTo('sales.view'));
    $inB = $this->tenant->run($this->fixture->b->business, fn () => $both->fresh()->hasPermissionTo('sales.view'));

    expect($inA)->toBeFalse()->and($inB)->toBeTrue();
});

it('doesn\'t carry roles loaded in one business over to another', function () {
    $both = $this->fixture->both;
    $this->tenant->run($this->fixture->a->business, fn () => $both->assignRole(sharedRole('manager')));
    $this->actingAs($both);

    $this->tenant->set($this->fixture->a->business);
    expect($both->hasRole('manager'))->toBeTrue();

    // Same signed-in user object, roles already loaded: switching clears them.
    $this->tenant->set($this->fixture->b->business);
    expect($both->hasRole('manager'))->toBeFalse();
});

it('shows a business its own roles and the shared templates, not another business\'s', function () {
    $ownRole = $this->tenant->run($this->fixture->a->business, fn () => Role::create(['name' => 'Stock Taker', 'guard_name' => 'web']));

    $inA = $this->tenant->run($this->fixture->a->business, fn () => Role::pluck('name')->all());
    $inB = $this->tenant->run($this->fixture->b->business, fn () => Role::pluck('name')->all());

    expect($ownRole->business_id)->toBe($this->fixture->a->business->id)
        ->and($inA)->toContain('Stock Taker', 'admin', 'cashier')
        ->and($inB)->toContain('admin', 'cashier')
        ->and($inB)->not->toContain('Stock Taker');
});

it('keeps businesses apart in the permission cache', function () {
    config(['permission.cache.store' => 'array']);
    app(PermissionRegistrar::class)->initializeCache();
    Permission::findOrCreate('reports.view');
    $both = $this->fixture->both;
    $this->tenant->run($this->fixture->a->business, fn () => $both->givePermissionTo('reports.view'));

    // Warm the cache in A, then ask in B.
    $this->tenant->run($this->fixture->a->business, fn () => $both->fresh()->hasPermissionTo('reports.view'));
    $inB = $this->tenant->run($this->fixture->b->business, fn () => $both->fresh()->hasPermissionTo('reports.view'));

    expect($inB)->toBeFalse();
});

it('returns the roles held in the default business at API sign-in', function () {
    $both = $this->fixture->both;
    $this->tenant->run($this->fixture->b->business, fn () => $both->assignRole(sharedRole('manager')));
    $both->update(['password' => 'secret-password']);
    $this->tenant->forget();

    // Default business is A, where the member is a cashier, not a manager.
    $response = $this->postJson('/api/login', ['email' => $both->email, 'password' => 'secret-password', 'device_name' => 'till'])
        ->assertOk();

    expect($response->json('data.user.roles'))->toBe(['cashier']);
});
