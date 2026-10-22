<?php

use App\Models\Business;
use App\Models\BusinessMembership;
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

test('new users can register', function () {
    $response = $this->post('/register', signupForm());

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice', absolute: false));
});

test('signup creates a business and its owner, waiting for the email to be verified (stage 3)', function () {
    $this->post('/register', signupForm());

    $user = User::where('email', 'owner@mamamboga.co.ke')->firstOrFail();
    $business = Business::where('name', 'Mama Mboga Stores')->firstOrFail();

    expect($business->status)->toBe(Business::STATUS_PENDING_VERIFICATION)
        ->and($business->trial_started_at)->toBeNull()
        ->and($user->default_business_id)->toBe($business->id)
        ->and($user->memberships()->where('business_id', $business->id)->value('status'))->toBe(BusinessMembership::STATUS_ACTIVE)
        ->and(app(TenantContext::class)->run($business, fn () => $user->fresh()->isBusinessOwner()))->toBeTrue();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('a new owner is sent to verify their email until they do', function () {
    $this->post('/register', signupForm());

    $this->get('/dashboard')->assertRedirect(route('verification.notice', absolute: false));
    $this->get(route('products.index'))->assertRedirect(route('verification.notice', absolute: false));
    $this->get(route('verification.notice'))->assertOk()->assertSee('Verify your email');
});

test('verifying the email opens the business and starts its trial', function () {
    $this->post('/register', signupForm());
    $user = User::where('email', 'owner@mamamboga.co.ke')->firstOrFail();

    $this->get(verificationUrl($user))->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    $business = Business::where('name', 'Mama Mboga Stores')->firstOrFail();
    expect($business->status)->toBe(Business::STATUS_ACTIVE)
        ->and($business->trial_started_at)->not->toBeNull();

    $this->get('/dashboard')->assertOk();
    $this->get(route('products.index'))->assertOk();
});

test('verifying again does not restart the trial', function () {
    $this->post('/register', signupForm());
    $user = User::where('email', 'owner@mamamboga.co.ke')->firstOrFail();
    $this->get(verificationUrl($user));
    $started = Business::where('name', 'Mama Mboga Stores')->value('trial_started_at');

    $this->travel(2)->days();
    $this->get(verificationUrl($user->fresh()));

    expect(Business::where('name', 'Mama Mboga Stores')->value('trial_started_at'))->toEqual($started);
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
