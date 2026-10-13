<?php

use Illuminate\Support\Facades\Process;

/**
 * The PreToolUse hook that stops destructive database commands
 * (.claude/hooks/block-destructive-db.sh). Every database except the test
 * database is treated as live, so these commands must be refused unless they
 * only target a "_testing" database.
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

it('blocks destructive commands against a live database', function (string $command) {
    expect(hookDecision($command))->toBe('deny');
})->with([
    'migrate:fresh' => 'php artisan migrate:fresh',
    'migrate --fresh' => 'php artisan migrate --fresh --seed',
    'migrate:refresh' => 'php artisan migrate:refresh',
    'migrate:reset' => 'php artisan migrate:reset',
    'migrate:rollback' => 'php artisan migrate:rollback --step=1',
    'db:wipe' => 'php artisan db:wipe',
    'migrate --force' => 'php artisan migrate --force',
    'bare db:seed' => 'php artisan db:seed',
    'drop table' => 'mysql -e "DROP TABLE sales"',
    'drop database' => 'mysql -e "drop database pos_saas_dev"',
    'truncate table' => 'mysql pos_saas_dev -e "TRUNCATE TABLE sales"',
    'truncate without TABLE' => 'mysql pos_saas_dev -e "truncate sales"',
    'delete without where' => 'mysql pos_saas_dev -e "DELETE FROM sales"',
    'uppercase' => 'PHP ARTISAN MIGRATE:FRESH',
    'live database named next to a test one' => 'DB_DATABASE=pos_saas_testing mysql fitness_center -e "truncate sales"',
    'UAT database next to a test one' => 'mysql pos_saas_testing fitness -e "truncate sales"',
    'UAT host next to a test one' => 'mysql -h 64.227.44.46 pos_saas_testing -e "truncate sales"',
    'production database next to a test one' => 'mysql -h 46.101.89.24 ecohub_pos_testing -e "truncate sales"',
]);

it('allows safe commands', function (string $command) {
    expect(hookDecision($command))->toBe('allow');
})->with([
    'plain migrate' => 'php artisan migrate',
    'migrate status' => 'php artisan migrate:status',
    'seeding one class' => 'php artisan db:seed --class=PermissionSeeder',
    'delete with where' => 'mysql pos_saas_dev -e "DELETE FROM sessions WHERE last_activity < 1"',
    'running the tests' => 'php artisan test --compact',
    'unrelated command' => 'git status',
]);

it('allows destructive commands that only target the test database', function (string $command) {
    expect(hookDecision($command))->toBe('allow');
})->with([
    'migrate:fresh on the test database' => 'DB_DATABASE=pos_saas_testing php artisan migrate:fresh',
    'truncate on the test database' => 'mysql pos_saas_testing -e "TRUNCATE TABLE sales"',
    'project path containing fitness' => 'cd /home/user/Projects/fitness-center && DB_DATABASE=pos_saas_testing php artisan migrate:fresh',
]);
