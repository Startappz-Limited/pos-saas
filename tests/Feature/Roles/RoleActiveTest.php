<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TwoBusinesses;

/**
 * Active and inactive roles, Phase 5 (A3): an inactive role grants nothing on
 * the web, the flat API and `/api/v1`, nor in the permissions sign-in and
 * `/api/user` report; the people holding it keep the assignment, so switching
 * it back on restores them. Switching a role off is confirmed first; the owner
 * role can't be switched off; an inactive role is given to nobody new.
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;

    // A custom role in A, held by a clerk: products, people and the business settings.
    [$this->role, $this->clerk] = $this->tenant->run($this->a->business, function (): array {
        $role = Role::create(['name' => 'clerk', 'guard_name' => 'web', 'business_id' => $this->a->business->id]);
        $role->syncPermissions(['products.view', 'users.view', 'settings.update']);
        $clerk = User::factory()->memberOf($this->a->business)->create(['password' => bcrypt('secret-pass')]);
        $clerk->assignRole($role);

        return [$role, $clerk];
    });
    $this->tenant->forget();
});

/**
 * A `/api/v1` token for $user bound to $business.
 */
function v1TokenFor(User $user, Business $business): string
{
    app(TenantContext::class)->forget();
    $token = $user->createToken('test', ['*']);
    $token->accessToken->forceFill(['business_id' => $business->id])->save();

    return $token->plainTextToken;
}

/**
 * Switches $role as A's owner, through the role screen.
 */
function switchRole(TwoBusinesses $fixture, Role $role, bool $active): void
{
    app(TenantContext::class)->forget();
    test()->actingAs($fixture->a->owner)
        ->patch(route('roles.active', $role), ['active' => $active ? '1' : '0', 'confirm' => '1'])
        ->assertSessionHasNoErrors()->assertSessionHas('success');
    app(TenantContext::class)->forget();
}

it('takes an inactive role\'s permissions away on the web, the flat API and /api/v1, and gives them back', function () {
    $grantsBefore = DB::table('model_has_roles')->where('role_id', $this->role->id)->get()->toArray();

    $this->actingAs($this->clerk)->get(route('products.index'))->assertOk();
    $this->tenant->forget();

    switchRole($this->fixture, $this->role, false);

    $this->actingAs($this->clerk)->get(route('products.index'))->assertForbidden();
    $this->tenant->forget();
    // (The flat API's product list checks no permission at all, so the people list is used.)
    Sanctum::actingAs($this->clerk, ['*']);
    $this->getJson('/api/users')->assertForbidden();
    $this->withToken(v1TokenFor($this->clerk, $this->a->business))->getJson('/api/v1/business/settings')->assertForbidden();

    switchRole($this->fixture, $this->role, true);
    $this->app['auth']->forgetGuards();

    $this->withToken(v1TokenFor($this->clerk, $this->a->business))->getJson('/api/v1/business/settings')->assertOk();
    $this->app['auth']->forgetGuards();
    $this->tenant->forget();
    Sanctum::actingAs($this->clerk->fresh(), ['*']);
    $this->getJson('/api/users')->assertOk();
    $this->app['auth']->forgetGuards();
    $this->tenant->forget();
    $this->actingAs($this->clerk)->get(route('products.index'))->assertOk();

    // The assignment never changed.
    expect(DB::table('model_has_roles')->where('role_id', $this->role->id)->get()->toArray())->toEqual($grantsBefore);
});

it('leaves an inactive role\'s permissions out of sign-in and /api/user, and keeps its name', function () {
    switchRole($this->fixture, $this->role, false);

    $flat = $this->postJson('/api/login', ['email' => $this->clerk->email, 'password' => 'secret-pass', 'device_name' => 'till'])->assertOk();
    expect($flat->json('data.user.permissions'))->not->toContain('products.view')
        ->and($flat->json('data.user.roles'))->toContain('clerk');

    $this->app['auth']->forgetGuards();
    $this->tenant->forget();
    $v1 = $this->postJson('/api/v1/auth/sign-in', ['email' => $this->clerk->email, 'password' => 'secret-pass', 'device_name' => 'till'])->assertOk();
    expect($v1->json('data.user.permissions'))->not->toContain('products.view')
        ->and($v1->json('data.user.roles'))->toContain('clerk');

    $this->app['auth']->forgetGuards();
    $this->tenant->forget();
    Sanctum::actingAs($this->clerk, ['*']);
    expect($this->getJson('/api/user')->assertOk()->json('data.permissions'))->not->toContain('products.view');

    switchRole($this->fixture, $this->role, true);
    $this->app['auth']->forgetGuards();
    Sanctum::actingAs($this->clerk->fresh(), ['*']);
    expect($this->getJson('/api/user')->assertOk()->json('data.permissions'))->toContain('products.view');
});

it('asks for the confirmation before switching a role off, and none to switch it back on', function () {
    $owner = $this->a->owner;

    $this->actingAs($owner)->patch(route('roles.active', $this->role), ['active' => '0'])
        ->assertSessionHasErrors(['confirm' => 'Confirm that the people with this role won\'t be able to do anything in the system with it until it\'s switched back on.']);
    $this->actingAs($owner)->patch(route('roles.active', $this->role), ['active' => '0', 'confirm' => '0'])->assertSessionHasErrors('confirm');
    expect($this->role->fresh()->is_active)->toBeTrue();

    $this->actingAs($owner)->patch(route('roles.active', $this->role), ['active' => '0', 'confirm' => '1'])->assertSessionHasNoErrors();
    expect($this->role->fresh()->is_active)->toBeFalse();

    $this->actingAs($owner)->patch(route('roles.active', $this->role), ['active' => '1'])->assertSessionHasNoErrors();
    expect($this->role->fresh()->is_active)->toBeTrue();
});

it('switches a role off from the edit form only with the confirmation, and leaves it alone when the field is absent', function () {
    $owner = $this->a->owner;

    $this->actingAs($owner)->put(route('roles.update', $this->role), ['name' => 'clerk', 'is_active' => '0'])
        ->assertSessionHasErrors('confirm');
    expect($this->role->fresh()->is_active)->toBeTrue();

    $this->actingAs($owner)->put(route('roles.update', $this->role), ['name' => 'clerk'])->assertSessionHasNoErrors();
    expect($this->role->fresh()->is_active)->toBeTrue();

    $this->actingAs($owner)->put(route('roles.update', $this->role), ['name' => 'clerk', 'is_active' => '0', 'confirm' => '1'])
        ->assertSessionHasNoErrors();
    expect($this->role->fresh()->is_active)->toBeFalse();
});

it('never switches off the owner role or a shared template, and lets no non-owner switch off a role they hold', function () {
    $this->actingAs($this->a->owner)->patch(route('roles.active', sharedRole('admin')), ['active' => '0', 'confirm' => '1'])->assertForbidden();
    expect(sharedRole('admin')->fresh()->is_active)->toBeTrue();

    $this->tenant->run($this->a->business, fn () => $this->role->givePermissionTo(['roles.view', 'roles.update']));
    $this->tenant->forget();
    $this->actingAs($this->clerk)->patch(route('roles.active', $this->role), ['active' => '0', 'confirm' => '1'])->assertForbidden();
    expect($this->role->fresh()->is_active)->toBeTrue();
});

it('refuses business B\'s role to business A (404), leaving it as it was', function () {
    $roleOfB = Role::withoutGlobalScopes()->where('business_id', $this->fixture->b->business->id)->where('name', 'cashier')->firstOrFail();

    $this->actingAs($this->a->owner)->patch(route('roles.active', $roleOfB), ['active' => '0', 'confirm' => '1'])->assertNotFound();

    expect($roleOfB->fresh()->is_active)->toBeTrue();
});

it('records switching a role in the audit log', function () {
    switchRole($this->fixture, $this->role, false);

    $audit = AuditLog::query()->where('auditable_type', Role::class)->where('auditable_id', $this->role->id)->latest('id')->first();

    expect($audit->new_values['is_active'] ?? null)->toBeFalse()
        ->and($audit->old_values['is_active'] ?? null)->toBeTrue()
        ->and($audit->business_id)->toBe($this->a->business->id)
        ->and($audit->user_id)->toBe($this->a->owner->id);
});

it('gives an inactive role to nobody new, but lets a holder keep it', function () {
    switchRole($this->fixture, $this->role, false);
    $owner = $this->a->owner;

    // Invite and Add User refuse it.
    $this->actingAs($owner)->post(route('invites.store'), ['email' => 'new@alpha.test', 'role_id' => $this->role->id])
        ->assertSessionHasErrors(['role_id' => 'This role is switched off. Switch it on before giving it to anyone.']);
    Sanctum::actingAs($owner, ['*']);
    $this->postJson('/api/users', ['name' => 'New', 'email' => 'api@alpha.test', 'password' => 'Secret-pass-1', 'roles' => ['clerk']])
        ->assertUnprocessable()->assertJsonValidationErrors('roles.0');

    // The clerk's own edit keeps it.
    $this->app['auth']->forgetGuards();
    $this->tenant->forget();
    Sanctum::actingAs($owner, ['*']);
    $this->putJson('/api/users/'.$this->clerk->uuid, ['roles' => ['clerk']])->assertOk();

    // Neither the Add User nor the invite form offers it.
    $this->app['auth']->forgetGuards();
    $this->tenant->forget();
    $form = $this->actingAs($owner)->get(route('users.create'))->assertOk()->getContent();
    $this->tenant->forget();
    $invites = $this->actingAs($owner)->get(route('invites.index'))->assertOk()->getContent();
    expect($form)->not->toMatch('/<option value="'.$this->role->id.'"/')
        ->and($invites)->not->toMatch('/<option value="'.$this->role->id.'"/')
        // ...while an active role is offered.
        ->and($form)->toMatch('/<option value="\d+"/');
});

it('shows the true state, a filter, and the dialog with the number of holders', function () {
    $owner = $this->a->owner;

    $this->actingAs($owner)->get(route('roles.index'))->assertOk()
        ->assertSee('data-swal-title="Switch this role off?"', false)
        ->assertSee('1 person in this business holds it.')
        ->assertSee('People with this role won&#039;t be able to do anything in the system with it until it&#039;s switched back on.', false);

    switchRole($this->fixture, $this->role, false);

    $inactive = $this->actingAs($owner)->get(route('roles.index', ['status' => 'inactive']))->assertOk()->viewData('roles');
    $active = $this->actingAs($owner)->get(route('roles.index', ['status' => 'active']))->assertOk()->viewData('roles');
    expect($inactive->pluck('name')->all())->toBe(['clerk'])
        ->and($active->pluck('name')->all())->not->toContain('clerk');

    $this->actingAs($owner)->get(route('roles.show', $this->role))->assertOk()->assertSee('Inactive')->assertSee('Switch on');
});
