<?php

use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    ownerRole();
    Notification::fake();
});

/**
 * @return array<string, string>
 */
function signupForm(array $overrides = []): array
{
    return [
        'name' => 'Test User',
        'business_name' => 'Mama Mboga Stores',
        'email' => 'owner@mamamboga.co.ke',
        'password' => 'Str0ng-P0S-Pass!',
        'password_confirmation' => 'Str0ng-P0S-Pass!',
        ...$overrides,
    ];
}

function verificationUrl(User $user): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
}

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200)->assertSee('Business Name');
});

test('new users can register and go straight to the system', function () {
    $response = $this->post('/register', signupForm());

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('signup creates an active business, its owner, a first shop and a trial (Phase 6)', function () {
    $this->post('/register', signupForm());

    $user = User::where('email', 'owner@mamamboga.co.ke')->firstOrFail();
    $business = Business::where('name', 'Mama Mboga Stores')->firstOrFail();

    expect($business->status)->toBe(Business::STATUS_ACTIVE)
        ->and($business->trial_started_at)->not->toBeNull()
        ->and($user->default_business_id)->toBe($business->id)
        ->and($user->memberships()->where('business_id', $business->id)->value('status'))->toBe(BusinessMembership::STATUS_ACTIVE)
        ->and(app(TenantContext::class)->run($business, fn () => $user->fresh()->isBusinessOwner()))->toBeTrue()
        ->and($user->verification_deadline_at)->not->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('a new owner can use the system at once, unverified', function () {
    $this->post('/register', signupForm());

    $this->get('/dashboard')->assertOk();
    $this->get(route('products.index'))->assertOk();
});

test('verifying the email keeps the business and does not restart the trial', function () {
    $this->post('/register', signupForm());
    $user = User::where('email', 'owner@mamamboga.co.ke')->firstOrFail();
    $business = Business::where('name', 'Mama Mboga Stores')->firstOrFail();
    $started = $business->trial_started_at;
    $ends = app(TenantContext::class)->run($business, fn () => Subscription::query()->value('trial_ends_at'));

    $this->travel(2)->days();
    $this->get(verificationUrl($user))->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    $business->refresh();
    expect($business->status)->toBe(Business::STATUS_ACTIVE)
        ->and($business->trial_started_at)->toEqual($started)
        ->and(app(TenantContext::class)->run($business, fn () => Subscription::query()->value('trial_ends_at')))->toEqual($ends);
});

test('a signup caught by the honeypot creates nothing', function () {
    $this->post('/register', signupForm(['my_name' => 'I am a bot']));

    $this->assertGuest();
    expect(User::where('email', 'owner@mamamboga.co.ke')->exists())->toBeFalse()
        ->and(Business::where('name', 'Mama Mboga Stores')->exists())->toBeFalse();
});

test('a business name is required', function () {
    $this->post('/register', signupForm(['business_name' => '']))->assertSessionHasErrors('business_name');

    expect(User::where('email', 'owner@mamamboga.co.ke')->exists())->toBeFalse();
});

test('a signed-in user with no business sees that, not an error', function () {
    $user = User::factory()->create();
    $user->memberships()->delete();
    $user->forceFill(['default_business_id' => null])->save();

    $this->actingAs($user)->get('/dashboard')->assertForbidden()->assertSee('No active business');
    $this->actingAs($user)->get(route('products.index'))->assertForbidden();
});
