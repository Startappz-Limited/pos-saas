<?php

use Tests\TestCase;

/*
 * These helpers read config(), which needs the application container. tests/Unit is
 * not bound to Tests\TestCase by tests/Pest.php, so bind it here — the app boots
 * but RefreshDatabase does not, so no database is touched.
 *
 * NOTE: tests/Feature/CurrencyHelpersTest.php is a duplicate of this file. Unit is
 * the right home (no HTTP, no DB); the Feature copy can be retired once the team
 * confirms nothing depends on it.
 */
uses(TestCase::class);

test('currency_symbol returns configured symbol', function () {
    config(['app.currency_symbol' => 'KSh']);

    expect(currency_symbol())->toBe('KSh');
});

test('currency_code returns configured code', function () {
    config(['app.currency_code' => 'KES']);

    expect(currency_code())->toBe('KES');
});

test('format_currency formats amount with symbol', function () {
    config(['app.currency_symbol' => 'KSh']);

    expect(format_currency(1234.56))->toBe('KSh1,234.56')
        ->and(format_currency(0))->toBe('KSh0.00')
        ->and(format_currency(null))->toBe('KSh0.00')
        ->and(format_currency(99.9))->toBe('KSh99.90');
});

test('format_currency respects custom decimals', function () {
    config(['app.currency_symbol' => 'KSh']);

    expect(format_currency(1234.567, 0))->toBe('KSh1,235')
        ->and(format_currency(1234.567, 3))->toBe('KSh1,234.567');
});

test('currency helpers use different symbols', function () {
    config(['app.currency_symbol' => '$']);
    expect(format_currency(100))->toBe('$100.00');

    config(['app.currency_symbol' => '€']);
    expect(format_currency(100))->toBe('€100.00');

    config(['app.currency_symbol' => '£']);
    expect(format_currency(100))->toBe('£100.00');
});
