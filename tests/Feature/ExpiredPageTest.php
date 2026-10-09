<?php

use Illuminate\Support\Facades\Route;

/**
 * A 419 (the form's CSRF token no longer matches the session, e.g. after
 * signing in as someone else in another tab) sends people back with an
 * explanation instead of an error page. Feature tests do not check CSRF, so a
 * route raises the 419 directly.
 */
beforeEach(function () {
    Route::middleware('web')->post('/_test/expired', fn () => abort(419));
});

it('sends a web form back with an explanation and the values typed', function () {
    $this->from('/profile')
        ->post('/_test/expired', ['name' => 'Typed name', 'password' => 'secret'])
        ->assertRedirect('/profile')
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'This page had expired'));

    expect(session()->getOldInput('name'))->toBe('Typed name')
        ->and(session()->getOldInput('password'))->toBeNull();
});

it('keeps the 419 for JSON and API callers', function () {
    $this->postJson('/_test/expired')->assertStatus(419);
});
