<?php

use App\Models\BusinessInvite;
use App\Models\BusinessMembership;
use App\Models\Role;
use App\Models\User;
use App\Notifications\BusinessInvitation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\Support\TwoBusinesses;

/**
 * Invites (decision 4.4, stage 3): by email, with a role and shops; an invite
 * to an email that already has a login adds a membership to it; an invite
 * can't grant more than its sender holds.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    Notification::fake();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->tenant = app(TenantContext::class);
    $this->tenant->forget();
});

function roleInA(string $name): Role
{
    return app(TenantContext::class)->run(test()->a->business, fn () => Role::findByName($name));
}

/**
 * Sends an invite as $sender and returns the link from the email.
 */
function inviteAs(User $sender, string $email, string $role = 'manager', array $shopIds = []): string
{
    app(TenantContext::class)->forget();
    test()->actingAs($sender)->post(route('invites.store'), ['email' => $email, 'role_id' => roleInA($role)->id, 'shop_ids' => $shopIds])
        ->assertSessionHasNoErrors()->assertRedirect(route('invites.index'));

    $url = null;
    Notification::assertSentTo(new AnonymousNotifiable, BusinessInvitation::class, function (BusinessInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($email, &$url): bool {
        if ($notifiable->routes['mail'] === $email) {
            $url = $notification->acceptUrl();
        }

        return true;
    });

    test()->post('/logout');
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();

    return $url;
}

it('creates a new login from an invite, verified, with the role and shops in the business', function () {
    $shop = $this->a->shops[1];
    $url = inviteAs($this->a->owner, 'new.person@bravo-shop.co.ke', 'manager', [$shop->id]);

    $invite = $this->tenant->run($this->a->business, fn () => BusinessInvite::firstOrFail());
    expect($invite->token_hash)->not->toContain(basename($url))
        ->and($invite->token_hash)->toBe(BusinessInvite::hashToken(basename($url)));

    $this->get($url)->assertOk()->assertSee('Choose a password');
    $this->post($url.'/register', ['name' => 'New Person', 'password' => 'Str0ng-P0S-Pass!', 'password_confirmation' => 'Str0ng-P0S-Pass!'])
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'new.person@bravo-shop.co.ke')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->canEnterBusiness($this->a->business))->toBeTrue()
        ->and($this->tenant->run($this->a->business, fn () => $user->fresh()->hasRole('manager')))->toBeTrue()
        ->and($this->tenant->run($this->a->business, fn () => $user->shops()->pluck('shops.id')->all()))->toBe([$shop->id])
        ->and($invite->fresh()->accepted_by)->toBe($user->id);

    $this->tenant->forget();
    $this->get('/dashboard')->assertOk();
});

it('adds a membership to the login an invited email already has', function () {
    $bOwner = $this->fixture->b->owner;
    $url = inviteAs($this->a->owner, $bOwner->email, 'cashier');
    $users = User::count();

    $this->get($url)->assertOk()->assertSee('Sign in');
    $this->get($url.'/sign-in')->assertRedirect(route('login'));
    $this->post('/login', ['email' => $bOwner->email, 'password' => 'password'])->assertRedirect($url);
    $this->tenant->forget();
    $this->get($url)->assertOk()->assertSee('Accept the invitation');
    $this->post($url.'/accept')->assertRedirect(route('dashboard'));

    expect(User::count())->toBe($users)
        ->and($bOwner->canEnterBusiness($this->a->business))->toBeTrue()
        ->and($bOwner->canEnterBusiness($this->fixture->b->business))->toBeTrue()
        ->and($this->tenant->run($this->a->business, fn () => $bOwner->fresh()->hasRole('cashier')))->toBeTrue()
        // Still B's owner in B, and not an owner in A.
        ->and($this->tenant->run($this->fixture->b->business, fn () => $bOwner->fresh()->isBusinessOwner()))->toBeTrue()
        ->and($this->tenant->run($this->a->business, fn () => $bOwner->fresh()->isBusinessOwner()))->toBeFalse();
});

it('restores a former member who is invited again', function () {
    $former = $this->a->cashier;
    BusinessMembership::where('business_id', $this->a->business->id)->where('user_id', $former->id)->update(['status' => 'inactive']);
    $url = inviteAs($this->a->owner, $former->email, 'cashier');

    $this->actingAs($former)->post($url.'/accept')->assertRedirect(route('dashboard'));

    expect($former->canEnterBusiness($this->a->business))->toBeTrue();
});

it('refuses an invite to a login with another email', function () {
    $url = inviteAs($this->a->owner, 'someone@bravo-shop.co.ke');
    $other = $this->fixture->b->owner;

    $this->actingAs($other)->get($url)->assertOk()->assertSee('You are signed in as');
    $this->actingAs($other)->post($url.'/accept')->assertRedirect();

    expect($other->canEnterBusiness($this->a->business))->toBeFalse()
        ->and($this->tenant->run($this->a->business, fn () => BusinessInvite::first()->accepted_at))->toBeNull();
});

it('refuses a used, withdrawn or expired invite', function (string $case) {
    $url = inviteAs($this->a->owner, 'late@bravo-shop.co.ke');
    $invite = $this->tenant->run($this->a->business, fn () => BusinessInvite::firstOrFail());
    $invite->update(match ($case) {
        'used' => ['accepted_at' => now()],
        'withdrawn' => ['revoked_at' => now()],
        'expired' => ['expires_at' => now()->subMinute()],
    });
    $this->tenant->forget();

    $this->get($url)->assertOk()->assertSee('no longer valid');
    $this->post($url.'/register', ['name' => 'Late', 'password' => 'Str0ng-P0S-Pass!', 'password_confirmation' => 'Str0ng-P0S-Pass!'])
        ->assertNotFound();
    expect(User::where('email', 'late@bravo-shop.co.ke')->exists())->toBeFalse();
})->with(['used', 'withdrawn', 'expired']);

it('sends a fresh link on resend and on a second invite, and only the newest works', function () {
    $first = inviteAs($this->a->owner, 'twice@bravo-shop.co.ke');
    $second = inviteAs($this->a->owner, 'twice@bravo-shop.co.ke');

    $this->get($first)->assertSee('no longer valid');
    $this->get($second)->assertSee('Choose a password');

    $invite = $this->tenant->run($this->a->business, fn () => BusinessInvite::query()->pending()->sole());
    $this->tenant->forget();
    $this->actingAs($this->a->owner)->post(route('invites.resend', $invite))->assertRedirect(route('invites.index'));

    $this->get($second)->assertSee('no longer valid');
});

it('withdraws an invite', function () {
    $url = inviteAs($this->a->owner, 'gone@bravo-shop.co.ke');
    $invite = $this->tenant->run($this->a->business, fn () => BusinessInvite::firstOrFail());
    $this->tenant->forget();

    $this->actingAs($this->a->owner)->delete(route('invites.destroy', $invite))->assertRedirect(route('invites.index'));

    $this->get($url)->assertSee('no longer valid');
});

it('lets only an owner invite an owner, and others only to roles within their own permissions', function () {
    // A member who may add users and view sales, nothing else.
    $lead = User::factory()->memberOf($this->a->business)->create();
    $this->tenant->run($this->a->business, function () use ($lead): void {
        $lead->givePermissionTo(['users.create', 'sales.view']);
        Role::create(['name' => 'sales viewer', 'guard_name' => 'web'])->givePermissionTo('sales.view');
    });
    $this->tenant->forget();

    foreach (['admin', 'cashier'] as $tooMuch) {
        $this->actingAs($lead)->post(route('invites.store'), ['email' => "{$tooMuch}@bravo-shop.co.ke", 'role_id' => roleInA($tooMuch)->id])
            ->assertSessionHasErrors('role_id');
    }
    $this->actingAs($lead)->post(route('invites.store'), ['email' => 'viewer@bravo-shop.co.ke', 'role_id' => roleInA('sales viewer')->id])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->a->owner)->post(route('invites.store'), ['email' => 'co-owner@bravo-shop.co.ke', 'role_id' => roleInA('admin')->id])
        ->assertSessionHasNoErrors();

    expect($this->tenant->run($this->a->business, fn () => BusinessInvite::pluck('email')->sort()->values()->all()))
        ->toBe(['co-owner@bravo-shop.co.ke', 'viewer@bravo-shop.co.ke']);
});

it('refuses another business\'s role or shop, an existing member, and a sender without users.create', function () {
    $roleOfB = app(TenantContext::class)->run($this->fixture->b->business, fn () => Role::findByName('manager'));
    $this->tenant->forget();

    $this->actingAs($this->a->owner)->post(route('invites.store'), ['email' => 'x@bravo-shop.co.ke', 'role_id' => $roleOfB->id])
        ->assertSessionHasErrors('role_id');
    $this->actingAs($this->a->owner)->post(route('invites.store'), ['email' => 'x@bravo-shop.co.ke', 'role_id' => roleInA('cashier')->id, 'shop_ids' => [$this->fixture->b->shops[0]->id]])
        ->assertSessionHasErrors('shop_ids.0');
    $this->actingAs($this->a->owner)->post(route('invites.store'), ['email' => $this->a->cashier->email, 'role_id' => roleInA('cashier')->id])
        ->assertSessionHasErrors('email');

    $this->actingAs($this->a->cashier)->get(route('invites.index'))->assertForbidden();
    $this->actingAs($this->a->cashier)->post(route('invites.store'), ['email' => 'y@bravo-shop.co.ke', 'role_id' => roleInA('cashier')->id])
        ->assertForbidden();

    expect($this->tenant->run($this->a->business, fn () => BusinessInvite::count()))->toBe(0);
    Notification::assertNothingSent();
});
