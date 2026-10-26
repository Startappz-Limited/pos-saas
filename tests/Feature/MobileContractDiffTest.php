<?php

/**
 * `mobile:contract-diff` compares the Flutter app's endpoint registry with the
 * routes, the flat API and `/api/v1` separately (Phase 7 step 1), and fails
 * with --fail-on-drift when the app calls a path that doesn't exist.
 */
function contractDiffApp(string $dart): string
{
    $dir = sys_get_temp_dir().'/flutter-app-'.uniqid();
    mkdir($dir.'/lib/core/constants', 0777, true);
    file_put_contents($dir.'/lib/core/constants/api_endpoints.dart', $dart);

    return $dir;
}

it('passes when every flat and v1 path the app calls exists, reporting each API apart', function () {
    $this->artisan('mobile:contract-diff', ['--app-path' => base_path('tests/Fixtures/flutter-app'), '--fail-on-drift' => true])
        ->expectsOutputToContain('Flat API (/api)')
        ->expectsOutputToContain('Versioned API (/api/v1)')
        ->assertSuccessful();
});

it('fails on a v1 path that does not exist, and never matches it against a flat route', function () {
    $app = contractDiffApp(<<<'DART'
        static const String sales = '/sales';
        static const String v1Sales = '/v1/sales';
        DART);

    $this->artisan('mobile:contract-diff', ['--app-path' => $app, '--fail-on-drift' => true])
        ->expectsOutputToContain('v1/sales')
        ->assertFailed();
});

it('fails on a flat path that does not exist', function () {
    $app = contractDiffApp("static const String nope = '/no-such-endpoint';");

    $this->artisan('mobile:contract-diff', ['--app-path' => $app, '--fail-on-drift' => true])->assertFailed();
});
