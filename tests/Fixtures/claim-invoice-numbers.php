<?php

/**
 * Claims invoice numbers in a process of its own, for
 * InvoiceNumberConcurrencyTest: php claim-invoice-numbers.php {shop} {register} {count} {start at} [adjustments]
 *
 * Waits until {start at} (a Unix time with microseconds) so the processes the
 * test starts begin together, then claims {count} numbers, one transaction
 * each as a sale would, and prints them as JSON. Runs against the database
 * named by DB_CONNECTION, which must be a test database.
 *
 * With `adjustments`, claims stock adjustment numbers for the shop instead
 * (stage 10, step 1g), and rolls every third claim back, as a save that fails
 * would: those numbers must be issued again, so the series keeps no gaps.
 */

use App\Models\Business;
use App\Services\InvoiceNumberService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $shopId, $registerId, $count, $startAt] = $argv;
$adjustments = ($argv[5] ?? null) === 'adjustments';

$connection = config('database.default');
TestCase::assertSafeTestDatabase(
    (string) config("database.connections.{$connection}.driver"),
    (string) config("database.connections.{$connection}.database"),
);

DB::connection()->getPdo();
time_sleep_until(max((float) $startAt, microtime(true) + 0.001));

$numbers = [];
// Adjustments are numbered inside the shop's business, as the app always is.
if ($adjustments) {
    $business = Business::query()->findOrFail(DB::table('shops')->where('id', $shopId)->value('business_id'));
    app(TenantContext::class)->set($business);
}
for ($i = 0; $i < (int) $count; $i++) {
    if ($adjustments && $i % 3 === 2) {
        DB::beginTransaction();
        app(InvoiceNumberService::class)->nextAdjustment((int) $shopId);
        DB::rollBack();
    }
    $numbers[] = DB::transaction(fn (): string => $adjustments
        ? app(InvoiceNumberService::class)->nextAdjustment((int) $shopId)
        : app(InvoiceNumberService::class)->next((int) $shopId, (int) $registerId));
}

echo json_encode($numbers);
