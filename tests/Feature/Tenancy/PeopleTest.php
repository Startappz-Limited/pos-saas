<?php

use App\Http\Middleware\ResolveTenant;
use App\Models\BusinessInvite;
use App\Models\BusinessMembership;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TwoBusinesses;

/**
 * People in a business (stage 3, UserPolicy): authorization on the flat API
 * as on the web, roles no larger than the giver's, owner protection, and
 * removal from one business only.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->tenant = app(TenantContext::class);

    // A member of A who may manage people and view sales, nothing else.
    $this->lead = User::factory()->memberOf($this->a->business)->create();
    $this->tenant->run($this->a->business, function (): void {
        $this->lead->givePermissionTo(['users.view', 'users.create', 'users.update', 'users.full-access', 'sales.view']);
        Role::create(['name' => 'sales viewer', 'guard_name' => 'web'])->givePermissionTo('sales.view');
    });
    $this->tenant->forget();
});

function aRole(string $name): Role
{
    return app(TenantContext::class)->run(test()->a->business, fn () => Role::findByName($name, 'web'));
}

function inA(Closure $callback): mixed
{
    return app(TenantContext::class)->run(test()->a->business, $callback);
}

/**
 * @return array<string, mixed>
 */
function newPersonForm(array $overrides = []): array
{
    return [
        'name' => 'New Person', 'email' => 'new.person@example.test',
        'password' => 'Str0ng-P0S-Pass!', 'password_confirmation' => 'Str0ng-P0S-Pass!',
        'status' => 'active', ...$overrides,
    ];
}

it('refuses the flat API\'s user endpoints to a member without the permissions', function () {
    Sanctum::actingAs($this->a->cashier, ['*']);
    $owner = $this->a->owner;

    $this->getJson('/api/users')->assertForbidden();
    $this->getJson('/api/users/roles')->assertForbidden();
    $this->getJson(route('api.users.show', $owner))->assertForbidden();
    $this->postJson('/api/users', ['name' => 'X', 'email' => 'x@example.test', 'password' => 'Str0ng-P0S-Pass!', 'roles' => ['admin']])
        ->assertForbidden();
    $this->putJson(route('api.users.update', $owner), ['email' => 'taken@example.test', 'password' => 'Str0ng-P0S-Pass!'])
        ->assertForbidden();
    $this->deleteJson(route('api.users.destroy', $owner))->assertForbidden();

    expect($owner->fresh()->email)->not->toBe('taken@example.test')
        ->and(User::where('email', 'x@example.test')->exists())->toBeFalse();
});

it('adds a person to the business, by invitation on the web and directly on the API, with a role held there', function () {
    // The web has one way to add someone: an invitation (Phase 6). Nobody sets a password for them.
    $this->actingAs($this->a->owner)->post(route('invites.store'), ['email' => 'new.person@bravo-shop.co.ke', 'role_id' => aRole('manager')->id])
        ->assertSessionHasNoErrors();
    expect(inA(fn () => BusinessInvite::query()->pending()->where('email', 'new.person@bravo-shop.co.ke')->value('role_id')))
        ->toBe(aRole('manager')->id);

    Sanctum::actingAs($this->a->owner, ['*']);
    $this->postJson('/api/users', ['name' => 'Api Person', 'email' => 'api.person@example.test', 'password' => 'Str0ng-P0S-Pass!', 'roles' => ['cashier']])
        ->assertCreated();

    foreach (['api.person@example.test' => 'cashier'] as $email => $role) {
        $user = User::where('email', $email)->firstOrFail();
        expect($user->canEnterBusiness($this->a->business))->toBeTrue()
            ->and(inA(fn () => $user->fresh()->hasRole($role)))->toBeTrue()
            ->and($user->canEnterBusiness($this->fixture->b->business))->toBeFalse();
    }
});

it('gives only roles within the giver\'s permissions, and the owner role only from an owner', function () {
    foreach (['admin', 'cashier'] as $tooMuch) {
        $this->actingAs($this->lead)->post(route('invites.store'), ['email' => "{$tooMuch}@bravo-shop.co.ke", 'role_id' => aRole($tooMuch)->id])
            ->assertSessionHasErrors('role_id');
    }
    Sanctum::actingAs($this->lead, ['*']);
    $this->postJson('/api/users', ['name' => 'X', 'email' => 'api.admin@example.test', 'password' => 'Str0ng-P0S-Pass!', 'roles' => ['admin']])
        ->assertUnprocessable()->assertJsonValidationErrors('roles.0');
    $this->putJson(route('api.users.update', $this->a->cashier), ['roles' => ['admin']])
        ->assertUnprocessable();

    $this->actingAs($this->lead)->post(route('invites.store'), ['email' => 'viewer@bravo-shop.co.ke', 'role_id' => aRole('sales viewer')->id])
        ->assertSessionHasNoErrors();

    expect(User::whereIn('email', ['admin@bravo-shop.co.ke', 'cashier@bravo-shop.co.ke', 'api.admin@example.test'])->exists())->toBeFalse()
        ->and(inA(fn () => BusinessInvite::query()->whereIn('email', ['admin@bravo-shop.co.ke', 'cashier@bravo-shop.co.ke'])->exists()))->toBeFalse()
        ->and(inA(fn () => $this->a->cashier->fresh()->hasRole('admin')))->toBeFalse();
});

it('lets only owners change, suspend or remove an owner', function () {
    $owner = $this->a->owner;

    $this->actingAs($this->lead)->post(route('users.suspend', $owner))->assertForbidden();
    $this->actingAs($this->lead)->post(route('users.deactivate', $owner))->assertForbidden();
    Sanctum::actingAs($this->lead, ['*']);
    $this->putJson(route('api.users.update', $owner), ['email' => 'hijacked@example.test'])->assertForbidden();

    // The same member may manage a cashier.
    $this->putJson(route('api.users.update', $this->a->cashier), ['name' => 'Renamed Cashier'])->assertOk();

    expect($owner->fresh()->status->value)->toBe('active')
        ->and($owner->fresh()->email)->not->toBe('hijacked@example.test')
        ->and($this->a->cashier->fresh()->name)->toBe('Renamed Cashier');
});

it('lets only owners remove people, even with users.full-access', function () {
    $this->actingAs($this->lead)->delete(route('users.destroy', $this->a->cashier))->assertForbidden();
    Sanctum::actingAs($this->lead, ['*']);
    $this->deleteJson(route('api.users.destroy', $this->a->cashier))->assertForbidden();

    expect($this->a->cashier->canEnterBusiness($this->a->business))->toBeTrue();
});

it('removes a person from this business only, and they lose access at once', function () {
    $both = $this->fixture->both;
    $tokenForA = $both->createToken('till');
    $tokenForA->accessToken->forceFill(['business_id' => $this->a->business->id])->save();

    $this->actingAs($this->a->owner)->delete(route('users.destroy', $both))->assertRedirect(route('users.index'));

    expect($both->fresh())->not->toBeNull()
        ->and(BusinessMembership::where('business_id', $this->a->business->id)->where('user_id', $both->id)->value('status'))->toBe('inactive')
        ->and($both->canEnterBusiness($this->a->business))->toBeFalse()
        ->and(inA(fn () => $both->fresh()->getRoleNames()->all()))->toBe([])
        ->and(inA(fn () => $both->shops()->whereIn('shops.id', collect($this->a->shops)->pluck('id'))->count()))->toBe(0)
        ->and(PersonalAccessToken::find($tokenForA->accessToken->id))->toBeNull()
        // Still a member of B, with B's role and shop.
        ->and($both->canEnterBusiness($this->fixture->b->business))->toBeTrue()
        ->and($this->tenant->run($this->fixture->b->business, fn () => $both->fresh()->hasRole('cashier')))->toBeTrue();

    // Their next request opens B, the only business left; A's customer is out of reach.
    $this->tenant->forget();
    app('auth')->forgetGuards();
    $this->actingAs($both->fresh())->get('/dashboard')->assertOk();
    expect(session(ResolveTenant::SESSION_KEY))->toBe($this->fixture->b->business->id);
});

it('never removes the remover, and keeps the business\'s last owner', function () {
    $owner = $this->a->owner;

    $this->actingAs($owner)->delete(route('users.destroy', $owner))->assertForbidden();
    Sanctum::actingAs($owner, ['*']);
    $this->deleteJson(route('api.users.destroy', $owner))->assertUnprocessable();
    $this->putJson(route('api.users.update', $owner), ['roles' => ['manager']])
        ->assertUnprocessable()->assertJsonValidationErrors('roles');

    expect($owner->canEnterBusiness($this->a->business))->toBeTrue()
        ->and(inA(fn () => $owner->fresh()->isBusinessOwner()))->toBeTrue();

    // With a co-owner, one owner may remove the other.
    $coOwner = User::factory()->memberOf($this->a->business)->create();
    inA(fn () => $coOwner->assignRole('admin'));
    $this->tenant->forget();
    app('auth')->forgetGuards();
    $this->actingAs($owner)->delete(route('users.destroy', $coOwner))->assertRedirect(route('users.index'));
    expect($coOwner->canEnterBusiness($this->a->business))->toBeFalse();
});
