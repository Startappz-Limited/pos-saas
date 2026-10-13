<?php

use Illuminate\Support\Facades\Process;

/**
 * The PreToolUse hook that stops destructive database commands
 * (.claude/hooks/block-destructive-db.sh). Every database except a test
 * database is treated as live, and the development database (UAT) holds data.
 *
 * lerd runs PHP in a container, so artisan commands are only trusted to hit a
 * test database when they say so with --database=<..._testing>; shell prefixes
 * such as DB_DATABASE=... never reach PHP (stage 0, step 4b).
 *
 * The cases live in tests/Fixtures/safety-hook-cases.php so they can also be
 * run on the host, where jq is installed.
 */
function hookDecision(string $command): string
{
    $result = Process::input(json_encode(['tool_input' => ['command' => $command]]))
        ->run(['bash', base_path('.claude/hooks/block-destructive-db.sh')]);

    expect($result->exitCode())->toBe(0);

    $output = json_decode($result->output(), true);

    return $output['hookSpecificOutput']['permissionDecision'] ?? 'allow';
}

beforeEach(function () {
    if (! Process::run(['which', 'jq'])->successful()) {
        $this->markTestSkipped('The hook needs jq.');
    }
});

$cases = require __DIR__.'/../Fixtures/safety-hook-cases.php';

it('blocks commands that change or destroy a live database', function (string $command) {
    expect(hookDecision($command))->toBe('deny');
})->with($cases['deny']);

it('allows commands that only read or only touch a test database', function (string $command) {
    expect(hookDecision($command))->toBe('allow');
})->with($cases['allow']);
