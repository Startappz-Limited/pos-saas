<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get('/reset-password/'.$notification->token);

        $response->assertStatus(200);

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'Str0ng-P0S-Pass!',
            'password_confirmation' => 'Str0ng-P0S-Pass!',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('the reset page has a show/hide button on both fields, the meter on the new one and a match line on the confirmation', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $html = $this->get('/reset-password/'.$notification->token)->assertOk()->getContent();

        expect(substr_count($html, 'data-password-toggle aria-controls'))->toBe(2)
            ->and(substr_count($html, 'autocomplete="new-password"'))->toBe(2)
            ->and($html)->toContain('name="password"')->toContain('name="password_confirmation"')
            ->toContain('id="password-strength"')->toContain('10 to 128 characters')
            ->toContain('data-personal="email"')->toContain('Not your email')
            ->toContain('id="password_confirmation-match"')->toContain('data-matches="password"')
            ->not->toContain('Minimum 8 characters');

        return true;
    });
});
