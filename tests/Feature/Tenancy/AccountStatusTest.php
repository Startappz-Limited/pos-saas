<?php

use App\Models\BusinessMembership;
use App\Models\User;
use App\Services\UserService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TwoBusinesses;

/**
 * Status (stage 3, decided 19 October 2026): suspending or deactivating from
 * a business's screens changes only the membership there; an account that
 * isn't active is signed out on the web and refused on the API; a member with
 * no usable business gets a clear refusal on the flat API.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->both = $this->fixture->both;
    $this->tenant = app(TenantContext::class);
    foreach ([$this->a->business, $this->fixture->b->business] as $business) {
        $this->tenant->run($business, fn () => $this->both->givePermissionTo('customers.view'));
    }
    $this->tenant->forget();
});

function membershipStatus(User $user, $business): ?string
{
    return BusinessMembership::where('business_id', $business->id)->where('user_id', $user->id)->value('status');
}

it('suspends someone in this business only, from the web and the API', function (string $channel) {
    if ($channel === 'web') {
        $this->actingAs($this->a->owner)->post(route('users.suspend', $this->both))->assertRedirect();
    } else {
        Sanctum::actingAs($this->a->owner, ['*']);
        $this->putJson(route('api.users.update', $this->both), ['status' => 'suspended'])->assertOk()
            ->assertJsonPath('data.status', 'suspended');
    }

    expect(membershipStatus($this->both, $this->a->business))->toBe('suspended')
        ->and(membershipStatus($this->both, $this->fixture->b->business))->toBe('active')
        ->and($this->both->fresh()->status->value)->toBe('active')
        ->and($this->both->canEnterBusiness($this->a->business))->toBeFalse();

    // Their next request opens B, where they still work; A is out of reach.
    $this->tenant->forget();
    app('auth')->forgetGuards();
    $this->actingAs($this->both->fresh())->get('/customers')->assertOk()
        ->assertSee('Bravo Customer')->assertDontSee('Alpha Customer');
})->with(['web', 'api']);

it('shows and filters by the status in the business', function () {
    $this->actingAs($this->a->owner)->post(route('users.deactivate', $this->both));

    Sanctum::actingAs($this->a->owner, ['*']);
    $this->getJson(route('api.users.show', $this->both))->assertOk()->assertJsonPath('data.status', 'inactive');
    $this->getJson('/api/users?status=inactive')->assertOk()
        ->assertJsonFragment(['email' => $this->both->email])
        ->assertJsonMissing(['email' => $this->a->owner->email]);

    // In B they're active, and so is the account.
    $this->tenant->forget();
    Sanctum::actingAs($this->fixture->b->owner, ['*']);
    $this->getJson(route('api.users.show', $this->both))->assertOk()->assertJsonPath('data.status', 'active');

    // Reactivating in A lets them back in.
    $this->tenant->forget();
    app('auth')->forgetGuards();
    $this->actingAs($this->a->owner)->post(route('users.activate', $this->both));
    expect($this->both->canEnterBusiness($this->a->business))->toBeTrue();
});

it('refuses an API status that does not exist instead of storing it', function () {
    Sanctum::actingAs($this->a->owner, ['*']);

    $this->putJson(route('api.users.update', $this->both), ['status' => 'banned'])->assertUnprocessable();

    expect(membershipStatus($this->both, $this->a->business))->toBe('active');
});

it('signs out an account that is no longer active, and refuses its sign-in and its tokens', function () {
    $this->actingAs($this->both)->get('/customers')->assertOk();
    $flat = $this->both->createToken('till')->plainTextToken;
    $v1 = $this->postJson('/api/v1/auth/sign-in', ['email' => $this->both->email, 'password' => 'password', 'device_name' => 'Till'])
        ->json('data.token');

    // Set account-wide (the platform operator's, Phase 5).
    DB::table('users')->where('id', $this->both->id)->update(['status' => 'suspended']);

    $this->tenant->forget();
    app('auth')->forgetGuards();
    $this->actingAs($this->both->fresh())->get('/customers')
        ->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post('/login', ['email' => $this->both->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();

    foreach ([[$flat, '/api/customers'], [$flat, '/api/user'], [$v1, '/api/v1/businesses']] as [$token, $uri]) {
        app('auth')->forgetGuards();
        $this->tenant->forget();
        $this->withHeaders(['Authorization' => "Bearer {$token}"])->getJson($uri)->assertForbidden();
    }
});

it('answers the flat API with 403 for a member with no usable business, and keeps the profile', function () {
    BusinessMembership::where('user_id', $this->a->cashier->id)->update(['status' => 'inactive']);
    Sanctum::actingAs($this->a->cashier->fresh(), ['*']);

    $this->getJson('/api/customers')->assertForbidden()->assertJsonPath('success', false);
    $this->getJson('/api/user')->assertOk();
    $this->postJson('/api/logout')->assertOk();
});

it('counts members by their status in the business', function () {
    $this->actingAs($this->a->owner)->post(route('users.suspend', $this->both));

    $stats = $this->tenant->run($this->a->business, fn () => app(UserService::class)->getStatistics());

    expect($stats['suspended'])->toBe(1)
        ->and($stats['active'])->toBe($stats['total'] - 1);
});

it('moves an account status set before businesses onto the memberships', function () {
    $former = User::factory()->memberOf($this->a->business)->create();
    $suspended = User::factory()->memberOf($this->a->business)->create();
    DB::table('users')->where('id', $former->id)->update(['status' => 'inactive']);
    DB::table('users')->where('id', $suspended->id)->update(['status' => 'suspended']);
    // As the legacy import left it: an inactive membership.
    BusinessMembership::where('user_id', $suspended->id)->update(['status' => 'inactive']);

    $migration = require database_path('migrations/2026_10_19_000002_move_user_status_onto_memberships.php');
    $migration->up();

    expect(membershipStatus($former, $this->a->business))->toBe('inactive')
        ->and(membershipStatus($suspended, $this->a->business))->toBe('suspended')
        ->and($former->fresh()->status->value)->toBe('active')
        ->and($suspended->fresh()->status->value)->toBe('active')
        ->and(membershipStatus($this->a->owner, $this->a->business))->toBe('active');

    $migration->down();

    expect($former->fresh()->status->value)->toBe('inactive')
        ->and($suspended->fresh()->status->value)->toBe('suspended')
        ->and($this->a->owner->fresh()->status->value)->toBe('active');
});
