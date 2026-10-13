<?php

/**
 * Commands and the decision .claude/hooks/block-destructive-db.sh must make.
 *
 * Used by tests/Feature/SafetyHookTest.php, and printable on the host (where
 * jq is installed) with:
 *   php -r 'foreach (require "tests/Fixtures/safety-hook-cases.php" as $want => $cases)
 *           foreach ($cases as $cmd) echo "$want\t$cmd\n";'
 */

return [
    'deny' => [
        // artisan commands that change the .env database (UAT)
        'plain migrate' => 'php artisan migrate',
        'migrate with --force' => 'php artisan migrate --force',
        'migrate --fresh' => 'php artisan migrate --fresh --seed',
        'migrate:fresh' => 'php artisan migrate:fresh',
        'migrate:refresh' => 'php artisan migrate:refresh',
        'migrate:reset' => 'php artisan migrate:reset',
        'migrate:rollback' => 'php artisan migrate:rollback --step=1',
        'db:wipe' => 'php artisan db:wipe',
        'bare db:seed' => 'php artisan db:seed',
        'seeding one class' => 'php artisan db:seed --class=PermissionSeeder',
        'uppercase' => 'PHP ARTISAN MIGRATE:FRESH',
        'shell prefix naming a test database' => 'DB_DATABASE=pos_saas_testing php artisan migrate:fresh',
        'the command that reached UAT (13 October 2026)' => 'export DB_CONNECTION=mysql_testing DB_TEST_DATABASE=pos_saas_rehearsal_testing && php artisan migrate --no-interaction && php artisan migrate:rollback --step=1 --no-interaction',
        'test connection next to a live database' => 'php artisan migrate --database=mysql_testing && mysql fitness -e "select 1"',
        'a connection that only contains testing' => 'php artisan migrate --database=mysql_testing_old',
        'one test connection and one live' => 'php artisan migrate --database=mysql_testing && php artisan migrate --database=mysql',
        'Artisan::call in tinker on the .env database' => "php artisan tinker --execute=\"Artisan::call('migrate');\"",
        'Artisan::call rollback in tinker' => "php artisan tinker --execute=\"Artisan::call('migrate:rollback', ['--step' => 1]);\"",

        // destructive SQL against a live database
        'drop table' => 'mysql -e "DROP TABLE sales"',
        'drop database' => 'mysql -e "drop database pos_saas_dev"',
        'truncate table' => 'mysql pos_saas_dev -e "TRUNCATE TABLE sales"',
        'truncate without TABLE' => 'mysql pos_saas_dev -e "truncate sales"',
        'delete without where' => 'mysql pos_saas_dev -e "DELETE FROM sales"',
        'eloquent truncate in tinker' => 'php artisan tinker --execute="App\Models\Sale::truncate();"',
        'schema drop in tinker' => "php artisan tinker --execute=\"Schema::drop('sales');\"",
        'live database named next to a test one' => 'mysql pos_saas_testing fitness_center -e "truncate sales"',
        'UAT database next to a test one' => 'mysql pos_saas_testing fitness -e "truncate sales"',
        'UAT host next to a test one' => 'mysql -h 64.227.44.46 pos_saas_testing -e "truncate sales"',
        'production database next to a test one' => 'mysql -h 46.101.89.24 ecohub_pos_testing -e "truncate sales"',
    ],

    'allow' => [
        'migrate status' => 'php artisan migrate:status',
        'running the tests' => 'php artisan test --compact',
        'running the tests on MySQL' => 'php artisan test --configuration=phpunit.mysql.xml',
        'migrate a test connection' => 'php artisan migrate --database=mysql_testing --no-interaction',
        'roll back a test connection' => 'php artisan migrate:rollback --step=1 --database=rehearsal_testing',
        'fresh on a test connection' => 'php artisan migrate:fresh --database=mysql_testing',
        'seed a test connection' => 'php artisan db:seed --class=PermissionSeeder --database=mysql_testing',
        'project path containing fitness' => 'cd /home/user/Projects/fitness-center && php artisan migrate --database=mysql_testing',
        'mysql client on a test database' => 'mysql pos_saas_testing -e "TRUNCATE TABLE sales"',
        'Artisan::call in tinker on a test connection' => "php artisan tinker --execute=\"Artisan::call('migrate', ['--database' => 'rehearsal_testing']);\"",
        'delete with where' => 'mysql pos_saas_dev -e "DELETE FROM sessions WHERE last_activity < 1"',
        'unrelated command' => 'git status',
    ],
];
