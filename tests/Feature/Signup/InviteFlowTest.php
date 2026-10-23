<?php

use App\Models\BusinessInvite;
use App\Models\BusinessMembership;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Notifications\BusinessInvitation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Support\TwoBusinesses;

/**
 * Adding a user is one flow (Phase 6, section 4): email and role, name
 * optional, the invitee sets their own password; links last 72 hours; pending
 * invites hold a staff seat; nothing is sent, resent or accepted while the
 * business is read-only or the role is switched off.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    Notification::fake();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->tenant = app(TenantContext::class);
    $this->tenant->forget();
});

function invite(array $data): TestResponse
{
    test()->tenant->forget();
    $roleId = app(TenantContext::class)->run(test()->a->business, fn () => Role::findByName($data['role'] ?? 'cashier')->id);
    unset($data['role']);

    return test()->actingAs(test()->a->owner)->post(route('invites.store'), ['role_id' => $roleId, ...$data]);
}

function invitationLinkFor(string $email): string
{
    $url = null;
    Notification::assertSentTo(new AnonymousNotifiable, BusinessInvitation::class, function ($n, $c, $notifiable) use ($email, &$url): bool {
        if ($notifiable->routes['mail'] === $email) {
            $url = $n->acceptUrl();
        }

        return true;
    });

    test()->post('/logout');
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();

    return $url;
}

function limitStaffTo(int $limit): void
{
    $plan = Plan::query()->where('key', 'tier-1')->firstOrFail();
    $plan->update(['limits' => [...$plan->limits, 'staff' => $limit]]);
    subscribe(test()->a->business, 'tier-1');
}

function activeStaff(): int
{
    return BusinessMembership::query()->where('business_id', test()->a->business->id)->where('status', 'active')->count();
}

it('adds a user with only an email and a role, and sets no password for them', function () {
    invite(['email' => 'amina@bravo-shop.co.ke'])->assertSessionHasNoErrors()->assertRedirect(route('invites.index'));

    $invite = $this->tenant->run($this->a->business, fn () => BusinessInvite::firstOrFail());
    expect($invite->name)->toBeNull()
        ->and($invite->expires_at->diffInHours(now(), true))->toBeBetween(71, 72)
        ->and(User::where('email', 'amina@bravo-shop.co.ke')->exists())->toBeFalse();
});

it('has the invitee set their own password and name on one page, and verifies them', function () {
    invite(['email' => 'amina@bravo-shop.co.ke']);
    $url = invitationLinkFor('amina@bravo-shop.co.ke');

    $this->get($url)->assertOk()->assertSee('Cashier')->assertSee('Choose a password');

    // A name is required when the inviter gave none.
    $this->post($url.'/register', ['name' => '', 'password' => 'purple tractors drink warm milk', 'password_confirmation' => 'purple tractors drink warm milk'])
        ->assertSessionHasErrors('name');

    $this->post($url.'/register', ['name' => 'Amina Otieno', 'password' => 'purple tractors drink warm milk', 'password_confirmation' => 'purple tractors drink warm milk'])
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'amina@bravo-shop.co.ke')->firstOrFail();
    expect($user->name)->toBe('Amina Otieno')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->verification_deadline_at)->toBeNull()
        ->and($user->canEnterBusiness($this->a->business))->toBeTrue()
        ->and($this->tenant->run($this->a->business, fn () => $user->fresh()->hasRole('cashier')))->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('pre-fills the name the inviter gave, editable', function () {
    invite(['email' => 'amina@bravo-shop.co.ke', 'name' => 'Amina Otieno']);
    $url = invitationLinkFor('amina@bravo-shop.co.ke');

    $this->get($url)->assertOk()->assertSee('value="Amina Otieno"', false);

    $this->post($url.'/register', ['name' => 'Amina O. Otieno', 'password' => 'purple tractors drink warm milk', 'password_confirmation' => 'purple tractors drink warm milk']);
    expect(User::where('email', 'amina@bravo-shop.co.ke')->value('name'))->toBe('Amina O. Otieno');
});

it('holds the invitee to the password rules', function (string $password) {
    invite(['email' => 'amina@bravo-shop.co.ke']);
    $url = invitationLinkFor('amina@bravo-shop.co.ke');

    $this->post($url.'/register', ['name' => 'Amina', 'password' => $password, 'password_confirmation' => $password])
        ->assertSessionHasErrors('password');
    expect(User::where('email', 'amina@bravo-shop.co.ke')->exists())->toBeFalse();
})->with(['Password123', 'Welcome2024', 'qwerty123456', 'amina@bravo-shop']);

it('refuses a placeholder address, and a person who is already a member', function () {
    invite(['email' => 'test@gmail.com'])->assertSessionHasErrors('email');
    invite(['email' => 'someone@mailinator.com'])->assertSessionHasErrors('email');
    invite(['email' => $this->a->cashier->email])->assertSessionHasErrors('email');
});

it('expires a link after 72 hours', function () {
    invite(['email' => 'amina@bravo-shop.co.ke']);
    $url = invitationLinkFor('amina@bravo-shop.co.ke');

    $this->travel(71)->hours();
    $this->get($url)->assertOk()->assertSee('Choose a password');

    $this->travel(2)->hours();
    $this->get($url)->assertOk()->assertSee('Ask your admin to send a new invite');
    $this->post($url.'/register', ['name' => 'Late', 'password' => 'purple tractors drink warm milk', 'password_confirmation' => 'purple tractors drink warm milk'])
        ->assertNotFound();
});

it('invalidates the old link on resend, and a revoked link stops working', function () {
    invite(['email' => 'amina@bravo-shop.co.ke']);
    $old = invitationLinkFor('amina@bravo-shop.co.ke');
    $invite = $this->tenant->run($this->a->business, fn () => BusinessInvite::firstOrFail());

    $this->tenant->forget();
    $this->actingAs($this->a->owner)->post(route('invites.resend', $invite))->assertRedirect(route('invites.index'));
    $this->post('/logout');
    app('auth')->forgetGuards();

    $this->get($old)->assertOk()->assertSee('Ask your admin to send a new invite');

    $this->tenant->forget();
    $this->actingAs($this->a->owner)->delete(route('invites.destroy', $invite))->assertRedirect(route('invites.index'));
    expect($this->tenant->run($this->a->business, fn () => $invite->fresh()->revoked_at))->not->toBeNull();
});

it('lets the admin change the role of a pending invite, within what they may give', function () {
    invite(['email' => 'amina@bravo-shop.co.ke']);
    $invite = $this->tenant->run($this->a->business, fn () => BusinessInvite::firstOrFail());
    $manager = $this->tenant->run($this->a->business, fn () => Role::findByName('manager')->id);

    $this->tenant->forget();
    $this->actingAs($this->a->owner)->patch(route('invites.role', $invite), ['role_id' => $manager])->assertRedirect(route('invites.index'));
    expect($this->tenant->run($this->a->business, fn () => $invite->fresh()->role_id))->toBe($manager);

    // A member who cannot give the owner role cannot hand it out here either.
    $lead = User::factory()->memberOf($this->a->business)->create();
    $this->tenant->run($this->a->business, fn () => $lead->givePermissionTo(['users.create', 'users.view']));
    $admin = $this->tenant->run($this->a->business, fn () => Role::findByName('admin')->id);
    $this->tenant->forget();
    $this->actingAs($lead)->patch(route('invites.role', $invite), ['role_id' => $admin])->assertSessionHasErrors('role_id');
});

it('counts pending invites against the staff limit and frees the seat on revoke', function () {
    limitStaffTo(activeStaff() + 1);

    invite(['email' => 'one@bravo-shop.co.ke'])->assertSessionHasNoErrors();
    invite(['email' => 'two@bravo-shop.co.ke'])->assertSessionHas('error');

    $invite = $this->tenant->run($this->a->business, fn () => BusinessInvite::firstOrFail());
    $this->tenant->forget();
    $this->actingAs($this->a->owner)->delete(route('invites.destroy', $invite));

    invite(['email' => 'two@bravo-shop.co.ke'])->assertSessionHasNoErrors();
});

it('lets the same person be invited again without taking a second seat', function () {
    limitStaffTo(activeStaff() + 1);

    invite(['email' => 'one@bravo-shop.co.ke'])->assertSessionHasNoErrors();
    invite(['email' => 'one@bravo-shop.co.ke'])->assertSessionHasNoErrors();

    expect($this->tenant->run($this->a->business, fn () => BusinessInvite::query()->pending()->count()))->toBe(1);
});

it('accepting uses the seat the invite held, not a second one', function () {
    limitStaffTo(activeStaff() + 1);
    invite(['email' => 'one@bravo-shop.co.ke']);
    $url = invitationLinkFor('one@bravo-shop.co.ke');

    $this->post($url.'/register', ['name' => 'One', 'password' => 'purple tractors drink warm milk', 'password_confirmation' => 'purple tractors drink warm milk'])
        ->assertRedirect(route('dashboard'));

    expect(User::where('email', 'one@bravo-shop.co.ke')->firstOrFail()->canEnterBusiness($this->a->business))->toBeTrue();
});

it('refuses to send, resend or accept while the business is read-only', function () {
    invite(['email' => 'amina@bravo-shop.co.ke']);
    $url = invitationLinkFor('amina@bravo-shop.co.ke');
    $invite = $this->tenant->run($this->a->business, fn () => BusinessInvite::firstOrFail());

    subscribe($this->a->business, 'tier-2', ['status' => 'active', 'current_period_end' => now()->subDay()]);

    $this->tenant->forget();
    $this->actingAs($this->a->owner)->post(route('invites.resend', $invite))->assertSessionHas('error');
    invite(['email' => 'other@bravo-shop.co.ke'])->assertSessionHas('error');
    expect(BusinessInvite::query()->withoutGlobalScopes()->where('email', 'other@bravo-shop.co.ke')->exists())->toBeFalse();

    $this->post('/logout');
    app('auth')->forgetGuards();
    $this->post($url.'/register', ['name' => 'Amina', 'password' => 'purple tractors drink warm milk', 'password_confirmation' => 'purple tractors drink warm milk'])
        ->assertSessionHas('error');
    expect(User::where('email', 'amina@bravo-shop.co.ke')->exists())->toBeFalse();
});

it('refuses an invite whose role was switched off, and makes no login for it', function () {
    invite(['email' => 'amina@bravo-shop.co.ke']);
    $url = invitationLinkFor('amina@bravo-shop.co.ke');
    $this->tenant->run($this->a->business, fn () => Role::findByName('cashier')->update(['is_active' => false]));

    $this->post($url.'/register', ['name' => 'Amina', 'password' => 'purple tractors drink warm milk', 'password_confirmation' => 'purple tractors drink warm milk'])
        ->assertRedirect($url)->assertSessionHas('error', 'The role for this invitation has been switched off. Ask your admin to send a new invitation.');

    expect(User::where('email', 'amina@bravo-shop.co.ke')->exists())->toBeFalse();
});

it('has no create-with-password path any more', function () {
    $this->actingAs($this->a->owner)->get(route('users.create'))->assertRedirect(route('invites.index'));
    $this->actingAs($this->a->owner)->post('/users', ['name' => 'X', 'email' => 'x@bravo-shop.co.ke', 'password' => 'purple tractors drink warm milk'])
        ->assertStatus(405);

    expect(User::where('email', 'x@bravo-shop.co.ke')->exists())->toBeFalse();
});

it('keeps another business\'s invites out of reach', function () {
    $other = $this->tenant->run($this->fixture->b->business, fn () => BusinessInvite::factory()->create(['email' => 'bravo@bravo-shop.co.ke']));

    $this->tenant->forget();
    $this->actingAs($this->a->owner)->get(route('invites.index'))->assertOk()->assertDontSee('bravo@bravo-shop.co.ke');
    foreach ([['post', 'invites.resend'], ['delete', 'invites.destroy'], ['patch', 'invites.role']] as [$method, $route]) {
        $this->tenant->forget();
        $this->actingAs($this->a->owner)->{$method}(route($route, $other), ['role_id' => 1])->assertNotFound();
    }
});
