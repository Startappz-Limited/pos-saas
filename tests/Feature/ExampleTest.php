<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    // `/` redirects to the login screen by design.
    $response->assertRedirect(route('login'));
});
