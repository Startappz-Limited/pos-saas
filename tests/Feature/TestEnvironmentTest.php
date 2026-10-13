<?php

use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

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

describe('the live-database guard', function () {
    it('refuses a database whose name does not end in _testing', function (string $driver, string $database) {
        expect(fn () => TestCase::assertSafeTestDatabase($driver, $database))
            ->toThrow(RuntimeException::class, "Refusing to run tests against '{$database}'");
    })->with([
        'the UAT development database' => ['mysql', 'fitness'],
        'the legacy local database' => ['mysql', 'fitness_center'],
        'a file-based SQLite database' => ['sqlite', 'database/database.sqlite'],
        'a name that only contains testing' => ['mysql', 'pos_saas_testing_old'],
        'an empty name' => ['mysql', ''],
    ]);

    it('accepts SQLite in memory and a _testing database', function (string $driver, string $database) {
        TestCase::assertSafeTestDatabase($driver, $database);

        expect(true)->toBeTrue();
    })->with([
        'SQLite in memory' => ['sqlite', ':memory:'],
        'the MySQL test database' => ['mysql', 'pos_saas_testing'],
    ]);

    it('checks the database this suite is actually running on', function () {
        $this->guardAgainstLiveDatabase();

        expect(config('database.connections.'.config('database.default').'.database'))
            ->toBeIn([':memory:', 'pos_saas_testing']);
    });
});
