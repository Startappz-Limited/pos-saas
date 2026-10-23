<?php

use App\Models\BusinessInvite;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\Sanctum;

/**
 * Passwords (fix/invite-first-sign-in): the first sign-in of an added user
 * asks only for a name and a password, with a show/hide toggle instead of a
 * confirmation; common and leaked passwords are refused even when the online
 * leak check can't be reached; and the same rule applies wherever a password
 * is set, on the web and the API.
 */
const LEAKED_MESSAGE = 'This password has appeared in data leaks, so it\'s one of the first that attackers try. Choose another; a few random words work well.';
const GOOD_PASSPHRASE = 'purple tractors drink warm milk';

function passwordErrors(string $password, array $data = []): array
{
    return Validator::make(['password' => $password, ...$data], ['password' => [new StrongPassword]])->errors()->get('password');
}

describe('the rule', function () {
    it('refuses common and leaked passwords, with letter swaps and added digits too', function (string $password) {
        expect(passwordErrors($password))->toBe([LEAKED_MESSAGE]);
    })->with(['Password123', 'password1234', 'P@ssw0rd123', 'Qwerty123!', 'Welcome2024!', 'iloveyou12', 'Sunshine2024', 'Football99!', 'M0nkey12345']);

    it('accepts a long passphrase and an unusual password', function (string $password) {
        expect(passwordErrors($password))->toBe([]);
    })->with([GOOD_PASSPHRASE, 'Kilimani-Mango-Tusker-77', 'zq8!Rv2#mW4pLx']);

    it('refuses a password built from the person\'s own name', function () {
        expect(passwordErrors('wanjikukamau2024', ['name' => 'Wanjiku Kamau']))->toBe(['The password must not contain your name, email or business name.']);
    });

    it('refuses a password the online check reports as leaked', function () {
        config(['security.signup.breach_check' => true]);
        $hash = strtoupper(sha1('Correct horse battery staple 42'));
        Http::fake(['api.pwnedpasswords.com/*' => Http::response(substr($hash, 5).":12\r\nAAAA:1\r\n")]);

        expect(passwordErrors('Correct horse battery staple 42'))->toBe([LEAKED_MESSAGE]);
    });

    it('still refuses common passwords when the online check is down, and blocks no one over it', function () {
        config(['security.signup.breach_check' => true]);
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 503)]);

        expect(passwordErrors('Password123'))->toBe([LEAKED_MESSAGE])
            ->and(passwordErrors(GOOD_PASSPHRASE))->toBe([]);
    });

    it('has a list of thousands of common passwords to check offline', function () {
        expect(count(file(database_path('data/common-passwords.txt'))))->toBeGreaterThan(10000);
    });
});

describe('first sign-in from an invite', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
        $this->token = str_repeat('b', 40);
        $tenant = app(TenantContext::class);
        $this->invite = BusinessInvite::factory()->create([
            'email' => 'amina@example.test', 'name' => 'Amina', 'token_hash' => BusinessInvite::hashToken($this->token),
        ]);
        $tenant->forget();
    });

    it('asks only for a name and a password, with a way to see the password', function () {
        $html = $this->get(route('invitations.show', $this->token))->assertOk()->getContent();

        expect($html)->toContain('name="name"')->toContain('value="Amina"')
            ->toContain('name="password"')->toContain('autocomplete="new-password"')
            ->toContain('id="password-toggle"')
            ->not->toContain('name="password_confirmation"')
            ->toContain('amina@example.test');
    });

    it('creates the account with no password confirmation', function () {
        $this->post(route('invitations.register', $this->token), ['name' => 'Amina Otieno', 'password' => GOOD_PASSPHRASE])
            ->assertSessionHasNoErrors()->assertRedirect();

        $user = User::query()->where('email', 'amina@example.test')->firstOrFail();
        expect($user->name)->toBe('Amina Otieno')
            ->and(Hash::check(GOOD_PASSPHRASE, $user->password))->toBeTrue();
    });

    it('refuses a leaked password, saying why, under the field', function () {
        $this->from(route('invitations.show', $this->token))
            ->post(route('invitations.register', $this->token), ['name' => 'Amina', 'password' => 'Password123'])
            ->assertSessionHasErrors(['password' => LEAKED_MESSAGE]);

        expect(User::query()->where('email', 'amina@example.test')->exists())->toBeFalse()
            ->and($this->get(route('invitations.show', $this->token))->getContent())
            ->toContain('id="password-error"')->toMatch('/id="password"[^>]*is-invalid/');
    });
});

describe('everywhere else a password is set', function () {
    beforeEach(function () {
        $this->owner = User::factory()->create(['password' => Hash::make('Old-pass-that-is-long-1')]);
        $this->owner->assignRole(ownerRole());
        $this->member = User::factory()->create();
        app(TenantContext::class)->forget();
    });

    it('refuses a leaked password when changing your own', function () {
        $this->actingAs($this->owner)->from('/profile')->put(route('password.update'), [
            'current_password' => 'Old-pass-that-is-long-1', 'password' => 'Password123', 'password_confirmation' => 'Password123',
        ])->assertSessionHasErrorsIn('updatePassword', ['password' => LEAKED_MESSAGE]);

        app(TenantContext::class)->forget();
        $this->actingAs($this->owner)->from('/profile')->put(route('password.update'), [
            'current_password' => 'Old-pass-that-is-long-1', 'password' => GOOD_PASSPHRASE, 'password_confirmation' => GOOD_PASSPHRASE,
        ])->assertSessionHasNoErrors();
        expect(Hash::check(GOOD_PASSPHRASE, $this->owner->fresh()->password))->toBeTrue();
    });

    it('refuses a leaked password set by an admin on the user edit form', function () {
        $this->actingAs($this->owner)->from(route('users.edit', $this->member))->put(route('users.update', $this->member), [
            'name' => $this->member->name, 'email' => $this->member->email, 'status' => 'active',
            'password' => 'Qwerty123!', 'password_confirmation' => 'Qwerty123!',
        ])->assertSessionHasErrors(['password' => LEAKED_MESSAGE]);
    });

    it('refuses a leaked password set by an admin through the API, on create and on update', function () {
        Sanctum::actingAs($this->owner, ['*']);

        $this->postJson('/api/users', ['name' => 'New', 'email' => 'new@example.test', 'password' => 'Welcome2024!'])
            ->assertUnprocessable()->assertJsonPath('errors.password.0', LEAKED_MESSAGE);
        $this->putJson('/api/users/'.$this->member->uuid, ['password' => 'Abc12345!'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    });
});
