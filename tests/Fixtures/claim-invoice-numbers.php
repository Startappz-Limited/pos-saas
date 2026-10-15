<?php

/**
 * Claims invoice numbers in a process of its own, for
 * InvoiceNumberConcurrencyTest: php claim-invoice-numbers.php {shop} {register} {count} {start at}
 *
 * Waits until {start at} (a Unix time with microseconds) so the processes the
 * test starts begin together, then claims {count} numbers, one transaction
 * each as a sale would, and prints them as JSON. Runs against the database
 * named by DB_CONNECTION, which must be a test database.
 */

use App\Services\InvoiceNumberService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $shopId, $registerId, $count, $startAt] = $argv;

$connection = config('database.default');
TestCase::assertSafeTestDatabase(
    (string) config("database.connections.{$connection}.driver"),
    (string) config("database.connections.{$connection}.database"),
);

DB::connection()->getPdo();
time_sleep_until(max((float) $startAt, microtime(true) + 0.001));

$numbers = [];
for ($i = 0; $i < (int) $count; $i++) {
    $numbers[] = DB::transaction(
        fn (): string => app(InvoiceNumberService::class)->next((int) $shopId, (int) $registerId)
    );
}

echo json_encode($numbers);
