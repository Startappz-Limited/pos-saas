<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Two registers of one shop numbering invoices at the same time, on MySQL 8
 * (stage 1b, decision 4.9).
 *
 * Several processes claim numbers together, so they have to see each other's
 * committed rows: the suite's own database runs each test in a transaction
 * no other process can see, so this test migrates and uses the scratch
 * database `concurrency_testing` (created if missing). It only ever holds the
 * rows this test writes.
 */
const CONCURRENCY_PROCESSES_PER_REGISTER = 3;
const CONCURRENCY_CLAIMS_PER_PROCESS = 20;

it('numbers two registers of a shop concurrently in separate series without collisions or gaps', function () {
    $config = config('database.connections.concurrency_testing');
    expect($config['database'])->toEndWith('_testing');

    // Create the database through a connection of its own: CREATE DATABASE
    // commits, and the test's own connection is inside a transaction.
    config(['database.connections.concurrency_server' => array_merge($config, ['database' => null])]);
    DB::connection('concurrency_server')->statement("CREATE DATABASE IF NOT EXISTS `{$config['database']}`");
    Artisan::call('migrate:fresh', ['--database' => 'concurrency_testing', '--force' => true]);

    $db = DB::connection('concurrency_testing');
    $businessId = $db->table('businesses')->insertGetId([
        'uuid' => (string) Str::uuid(), 'name' => 'Concurrency', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $shopId = $db->table('shops')->insertGetId([
        'business_id' => $businessId, 'uuid' => (string) Str::uuid(), 'name' => 'Nairobi', 'code' => 'NRB',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $registers = [];
    foreach (['R1', 'R2'] as $code) {
        $registers[$code] = $db->table('registers')->insertGetId([
            'uuid' => (string) Str::uuid(), 'shop_id' => $shopId, 'name' => "Till {$code}", 'code' => $code,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $startAt = microtime(true) + 3;
    $results = Process::pool(function ($pool) use ($registers, $shopId, $startAt): void {
        foreach ($registers as $code => $registerId) {
            foreach (range(1, CONCURRENCY_PROCESSES_PER_REGISTER) as $n) {
                $pool->as("{$code}-{$n}")
                    ->path(base_path())
                    ->env(['DB_CONNECTION' => 'concurrency_testing'])
                    ->timeout(120)
                    ->command([PHP_BINARY, 'tests/Fixtures/claim-invoice-numbers.php', $shopId, $registerId, CONCURRENCY_CLAIMS_PER_PROCESS, sprintf('%.6F', $startAt)]);
            }
        }
    })->start()->wait();

    $issued = ['R1' => [], 'R2' => []];
    foreach ($results->collect() as $key => $result) {
        expect($result->successful())->toBeTrue("{$key} failed: ".$result->errorOutput());
        array_push($issued[substr($key, 0, 2)], ...json_decode($result->output(), true));
    }

    $year = now()->format('Y');
    $total = CONCURRENCY_PROCESSES_PER_REGISTER * CONCURRENCY_CLAIMS_PER_PROCESS;
    foreach ($issued as $code => $numbers) {
        sort($numbers);
        $expected = array_map(fn (int $n): string => sprintf('INV-NRB-%s-%s-%06d', $code, $year, $n), range(1, $total));

        // Every number once: no collisions, no gaps, nothing from the other till's series.
        expect($numbers)->toBe($expected)
            ->and((int) $db->table('registers')->where('id', $registers[$code])->value('last_invoice_number'))->toBe($total);
    }
})->skip(fn () => DB::getDriverName() !== 'mysql', 'Needs MySQL 8: runs with phpunit.mysql.xml.');
