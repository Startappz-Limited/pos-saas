<?php

use App\Models\Business;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\EmailAddressChanged;
use App\Support\Signup\VerificationLink;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/**
 * Email verification with a 24-hour grace period (Phase 6, section 3): a new
 * owner works at once; after 24 hours unverified, everything but verifying,
 * resending, changing the email and signing out is refused; verifying
 * restores access immediately.
 */
beforeEach(function () {
    ownerRole();
    Notification::fake();
    $this->post('/register', [
        'name' => 'Wanjiku Kamau', 'business_name' => 'Mama Mboga Stores', 'email' => 'wanjiku@mamamboga.co.ke',
        'password' => 'purple tractors drink warm milk', 'agree_terms' => '1',
    ]);
    $this->owner = User::where('email', 'wanjiku@mamamboga.co.ke')->firstOrFail();
    $this->business = Business::findOrFail($this->owner->default_business_id);
    auth()->logout();
});

function blocked($test): void
{
    $test->actingAs(test()->owner)->get('/dashboard')->assertRedirect(route('verification.notice', absolute: false));
}

it('works at 23h59 and is blocked at 24h01', function () {
    $this->travel(23)->hours();
    $this->travel(59)->minutes();
    $this->actingAs($this->owner)->get('/dashboard')->assertOk();

    $this->travel(2)->minutes();
    blocked($this);
});

it('blocks the web, the flat API and /api/v1 with email_verification_required', function () {
    $this->travel(25)->hours();

    $this->actingAs($this->owner)->get(route('products.index'))->assertRedirect(route('verification.notice'));

    Sanctum::actingAs($this->owner);
    $this->getJson('/api/customers')->assertForbidden()->assertJsonPath('code', 'email_verification_required');
    $this->getJson('/api/v1/billing')->assertForbidden()->assertJsonPath('code', 'email_verification_required');
    $this->postJson('/api/v1/billing/shops', ['shop_count' => 2])->assertForbidden()->assertJsonPath('code', 'email_verification_required');
});

it('still allows verifying, resending, changing the email and signing out', function () {
    $this->travel(25)->hours();
    $this->actingAs($this->owner);

    $this->get(route('verification.notice'))->assertOk()->assertSee('paused');
    $this->post(route('verification.send'))->assertSessionHas('status', 'verification-link-sent');
    $this->put(route('verification.email.update'), ['email' => 'wanjiku.new@mamamboga.co.ke', 'current_password' => 'purple tractors drink warm milk'])
        ->assertSessionHasNoErrors();
    $this->post(route('logout'))->assertRedirect();
});

it('restores access the moment the email is verified, and the trial clock never paused', function () {
    $ends = app(TenantContext::class)->run($this->business, fn () => Subscription::query()->value('trial_ends_at'));
    $this->travel(30)->hours();
    blocked($this);

    $this->actingAs($this->owner)->get(VerificationLink::url($this->owner))->assertRedirect();

    $this->actingAs($this->owner->fresh())->get('/dashboard')->assertOk();
    expect($this->owner->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and(app(TenantContext::class)->run($this->business, fn () => Subscription::query()->value('trial_ends_at')))->toEqual($ends);
});

it('never blocks staff who were not put on the clock', function () {
    $staff = User::factory()->create(['email_verified_at' => null, 'verification_deadline_at' => null]);

    $this->travel(5)->days();
    $this->actingAs($staff)->get(route('verification.notice'))->assertOk();
    expect($staff->mustVerifyEmailNow())->toBeFalse();
});

it('starts a new 24 hours when the email changes, mails the new address and tells the old', function () {
    $this->travel(20)->hours();
    $this->freezeTime();

    $this->actingAs($this->owner)->put(route('verification.email.update'), [
        'email' => 'wanjiku.new@mamamboga.co.ke', 'current_password' => 'purple tractors drink warm milk',
    ])->assertRedirect(route('verification.notice'));

    $owner = $this->owner->fresh();
    expect($owner->email)->toBe('wanjiku.new@mamamboga.co.ke')
        ->and($owner->email_verified_at)->toBeNull()
        ->and($owner->verification_deadline_at->toDateTimeString())->toBe(now()->addHours(24)->toDateTimeString());

    Notification::assertSentTo($owner, VerifyEmail::class);
    Notification::assertSentOnDemand(EmailAddressChanged::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'wanjiku@mamamboga.co.ke');

    // Works for a fresh 24 hours, not the old deadline.
    $this->travel(10)->hours();
    $this->actingAs($owner)->get('/dashboard')->assertOk();
});

it('refuses an email change without the password, a placeholder address or one already taken', function () {
    $taken = User::factory()->create(['email' => 'taken@mamamboga.co.ke']);
    $this->actingAs($this->owner);

    $this->put(route('verification.email.update'), ['email' => 'x@mamamboga.co.ke', 'current_password' => 'wrong'])
        ->assertSessionHasErrorsIn('changeEmail', 'current_password');
    $this->put(route('verification.email.update'), ['email' => 'test@gmail.com', 'current_password' => 'purple tractors drink warm milk'])
        ->assertSessionHasErrorsIn('changeEmail', 'email');
    $this->put(route('verification.email.update'), ['email' => 'taken@mamamboga.co.ke', 'current_password' => 'purple tractors drink warm milk'])
        ->assertSessionHasErrorsIn('changeEmail', 'email');

    expect($this->owner->fresh()->email)->toBe('wanjiku@mamamboga.co.ke');
});

it('treats a profile email change the same way', function () {
    $this->actingAs($this->owner)->patch(route('profile.update'), ['name' => 'Wanjiku Kamau', 'email' => 'wanjiku.profile@mamamboga.co.ke']);

    expect($this->owner->fresh()->email_verified_at)->toBeNull()
        ->and($this->owner->fresh()->verification_deadline_at)->not->toBeNull();
    Notification::assertSentOnDemand(EmailAddressChanged::class);
});

it('accepts only the newest verification link, for 48 hours', function () {
    $old = VerificationLink::url($this->owner);
    $new = VerificationLink::url($this->owner);

    $this->actingAs($this->owner)->get($old)->assertRedirect(route('verification.notice'))->assertSessionHas('error');
    expect($this->owner->fresh()->hasVerifiedEmail())->toBeFalse();

    $this->travel(47)->hours();
    $this->actingAs($this->owner)->get($new)->assertRedirect();
    expect($this->owner->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('lets a link expire after 48 hours', function () {
    $link = VerificationLink::url($this->owner);

    $this->travel(49)->hours();
    $this->actingAs($this->owner)->get($link)->assertForbidden();
    expect($this->owner->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('cannot use a link twice', function () {
    $link = VerificationLink::url($this->owner);
    Event::fake([Verified::class]);

    $this->actingAs($this->owner)->get($link)->assertRedirect();
    $verifiedAt = $this->owner->fresh()->email_verified_at;

    $this->travel(1)->hour();
    $this->actingAs($this->owner->fresh())->get($link)->assertRedirect();

    Event::assertDispatchedTimes(Verified::class, 1);
    expect($this->owner->fresh()->email_verified_at)->toEqual($verifiedAt);
});

it('limits resending to one a minute and five an hour', function () {
    $this->actingAs($this->owner);

    $this->post(route('verification.send'))->assertSessionHas('status');
    $this->post(route('verification.send'))->assertStatus(429);

    foreach (range(1, 4) as $i) {
        $this->travel(61)->seconds();
        $this->post(route('verification.send'))->assertSessionHas('status');
    }

    $this->travel(61)->seconds();
    $this->post(route('verification.send'))->assertStatus(429);
});

it('shows the banner with the hours left, and not once the account is paused', function () {
    $this->travel(5)->hours();
    $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertSee('Verify your email — 19 hours left');
});
