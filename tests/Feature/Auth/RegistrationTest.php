<?php

use App\Enums\Feature;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Signup\VerificationLink;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

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
        'agree_terms' => '1',
        ...$overrides,
    ];
}

function verificationUrl(User $user): string
{
    return VerificationLink::url($user);
}

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200)->assertSee('Business name');
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

/*
 * The sign-up page (fix/signup-page-ui): four fields and the terms, the Free
 * Trial chosen by default with the other plans folded away, no password
 * confirmation, and errors under their fields.
 */

test('signing up needs no password confirmation', function () {
    $this->post('/register', signupForm())->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'owner@mamamboga.co.ke')->exists())->toBeTrue();
});

test('the terms must be accepted, and nothing is created otherwise', function () {
    $form = signupForm();
    unset($form['agree_terms']);

    $this->from('/register')->post('/register', $form)
        ->assertRedirect('/register')
        ->assertSessionHasErrors(['agree_terms' => 'Please accept the Terms and Privacy Policy to continue.']);

    expect(User::where('email', 'owner@mamamboga.co.ke')->exists())->toBeFalse();
});

test('the page asks only for name, business, email, password and the terms', function () {
    $html = $this->get('/register')->assertOk()->getContent();

    expect($html)->not->toContain('name="password_confirmation"')
        ->toContain('autocomplete="name"')->toContain('autocomplete="organization"')
        ->toContain('autocomplete="email"')->toContain('autocomplete="new-password"')
        ->toContain('id="password-toggle"')
        ->toMatch('/type="checkbox"[^>]*name="agree_terms"/')
        // The honeypot is on the form.
        ->toContain('name="valid_from"');
});

test('the Free Trial is chosen, the plan cards wait in a window, and there is no shop count', function () {
    $html = $this->get('/register')->assertOk()->getContent();

    expect($html)->toMatch('/id="plan-free-trial"[^>]*checked/')
        ->toContain('id="plan-modal"')
        ->toMatch('/data-bs-target="#plan-modal"/')
        ->not->toContain('data-open-on-load')
        ->not->toContain('name="shop_count"')
        ->and(substr_count($html, 'name="plan"'))->toBe(4)
        ->and(substr_count($html, 'form="signup-form"'))->toBe(4);
});

test('each plan card shows its price, limits and features from plan data', function () {
    $html = $this->get('/register')->assertOk()->getContent();

    expect($html)->toContain('KES 1,500')->toContain('KES 3,000')->toContain('KES 5,000')
        ->toContain('Unlimited')
        ->toContain(Feature::EcommerceSync->label())
        ->toContain('Not included:')->toContain('Included:')
        ->and(substr_count($html, 'class="card plan-card'))->toBe(4);
});

test('a pricing page link preselects a tier without opening the window', function () {
    $html = $this->get('/register?plan=tier-2')->assertOk()->getContent();

    expect($html)->toMatch('/id="plan-tier-2"[^>]*checked/')
        ->toMatch('/id="plan-summary">Tier 2</')
        ->toContain('KES 3,000 per shop a month after the 14-day trial')
        ->not->toContain('data-open-on-load');
});

test('a plan error opens the window', function () {
    $this->from('/register')->post('/register', signupForm(['plan' => 'enterprise']));

    expect($this->get('/register')->getContent())->toContain('data-open-on-load="1"')->toContain('id="plan-error"');
});

test('a shop count sent with the form is ignored: the trial pays for one shop', function () {
    $this->post('/register', signupForm(['plan' => 'tier-2', 'shop_count' => 5]))->assertRedirect();

    $business = Business::where('name', 'Mama Mboga Stores')->firstOrFail();

    expect(app(TenantContext::class)->run($business, fn () => Subscription::query()->firstOrFail()->shop_count))->toBe(1);
});

test('shows each error under its field, marked for screen readers', function () {
    $this->from('/register')->post('/register', signupForm(['name' => '', 'email' => 'not-an-email']));

    $html = $this->get('/register')->getContent();

    expect($html)->toMatch('/id="name"[^>]*is-invalid/')
        ->toContain('id="name-error"')->toContain('aria-describedby="name-error"')
        ->toContain('id="email-error"')
        ->toContain('problems with the form');
});

test('links the terms when their pages are configured, and names them otherwise', function () {
    expect($this->get('/register')->getContent())->toContain('I agree to the')->not->toContain('href="https://example.test/terms"');

    config(['legal.terms_url' => 'https://example.test/terms', 'legal.privacy_url' => 'https://example.test/privacy']);

    $this->get('/register')->assertSee('href="https://example.test/terms"', false)->assertSee('href="https://example.test/privacy"', false);
});
