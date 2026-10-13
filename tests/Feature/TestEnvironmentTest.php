<?php

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Support\Facades\URL;

/**
 * The suite must give the same result on every machine, whatever the
 * developer's .env holds. These settings changed test outcomes before
 * phpunit.xml pinned them (stage 0, step 2).
 */
it('generates plain http URLs regardless of the local APP_URL', function () {
    expect(config('app.url'))->toBe('http://localhost')
        ->and(URL::to('/dashboard'))->toBe('http://localhost/dashboard');
});

it('starts with WhatsApp unconfigured', function () {
    expect(WhatsAppChannel::isConfigured())->toBeFalse();
});

it('lets a test configure WhatsApp explicitly', function () {
    configureWhatsApp();

    expect(WhatsAppChannel::isConfigured())->toBeTrue();
});
