<?php

use App\Support\DatabaseSnapshot;
use Database\Seeders\LiveLikeDatasetSeeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

/**
 * The live-like dataset (stage 0, step 6): `dataset:build` only builds into an
 * empty _testing database, and the same seed and scale give the same data on
 * every build.
 *
 * The builds run on the dataset_testing scratch database, never the test
 * database: the build switches the default connection and blocks every other.
 */
const DATASET_SCALE = 0.05;

/**
 * Columns encrypted with a random IV: the ciphertext differs on every build
 * by design, so they're compared decrypted.
 *
 * @var array<string, list<string>>
 */
const ENCRYPTED_COLUMNS = ['abandoned_carts' => ['checkout_link']];

/**
 * One fingerprint per table: CHECKSUM TABLE, or a hash of the rows with the
 * encrypted columns decrypted.
 *
 * @return array<string, string>
 */
function datasetFingerprint(): array
{
    $sums = app(DatabaseSnapshot::class)->checksums('dataset_testing');

    foreach (ENCRYPTED_COLUMNS as $table => $columns) {
        $rows = DB::connection('dataset_testing')->table($table)->orderBy('id')->get()
            ->map(function (object $row) use ($columns): array {
                $row = (array) $row;
                foreach ($columns as $column) {
                    $row[$column] = $row[$column] === null ? null : Crypt::decryptString($row[$column]);
                }

                return $row;
            });
        $sums[$table] = md5(json_encode($rows));
    }

    return $sums;
}

function emptyDatasetDatabase(): void
{
    Schema::connection('dataset_testing')->dropAllTables();
}

describe('building on MySQL', function () {
    beforeEach(function () {
        // Pest runs this before a describe-level skip, so skip here first.
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Needs MySQL: runs with phpunit.mysql.xml.');
        }

        emptyDatasetDatabase();
    });

    afterEach(function () {
        if (DB::getDriverName() === 'mysql' || config('database.default') === 'dataset_testing') {
            emptyDatasetDatabase();
        }
    });

    it('builds the same data every time', function () {
        // Each build is its own process, exactly as `php artisan dataset:build`
        // is run: within one PHP process, state held over from a first build
        // (static caches) changes a second one's random sequence.
        $build = fn () => Process::path(base_path())->timeout(900)
            ->run([PHP_BINARY, 'artisan', 'dataset:build', '--database=dataset_testing', '--scale='.DATASET_SCALE]);

        expect($build()->successful())->toBeTrue();
        $first = datasetFingerprint();

        emptyDatasetDatabase();
        expect($build()->successful())->toBeTrue();
        $second = datasetFingerprint();

        $differing = array_keys(array_diff_assoc($first, $second));
        expect($differing)->toBe([], 'Tables that differ between builds: '.implode(', ', $differing))
            ->and($second)->toBe($first);
    });

    it('covers what a system in use holds', function () {
        $this->artisan('dataset:build', ['--database' => 'dataset_testing', '--scale' => DATASET_SCALE])->assertSuccessful();
        $db = DB::connection('dataset_testing');

        // Several shops; staff assigned to some shops, to two, and to none.
        expect($db->table('shops')->whereNull('deleted_at')->count())->toBe(5)
            ->and($db->table('users')->count())->toBeGreaterThan(20)
            ->and($db->table('users')->whereNotIn('id', $db->table('shop_user')->select('user_id'))->count())->toBeGreaterThan(0)
            ->and($db->table('shop_user')->select('user_id')->groupBy('user_id')->havingRaw('count(*) > 1')->get())->not->toBeEmpty();

        // Products with and without variations; per-shop stock.
        expect($db->table('products')->where('has_variations', true)->count())->toBeGreaterThan(0)
            ->and($db->table('products')->where('has_variations', false)->count())->toBeGreaterThan(0)
            ->and($db->table('product_variations')->count())->toBeGreaterThan(0)
            ->and($db->table('shop_product')->count())->toBeGreaterThan(0);

        // Stock and purchasing.
        foreach (['stock_intakes', 'stock_adjustments', 'stock_adjustment_items', 'stock_movements', 'purchase_orders', 'purchase_order_items', 'purchase_returns', 'purchase_return_items'] as $table) {
            expect($db->table($table)->count())->toBeGreaterThan(0, "{$table} is empty");
        }

        // Sales through all four paths, with items, payments, returns and refunds.
        expect($db->table('sales')->count())->toBe((int) round(3000 * DATASET_SCALE))
            ->and($db->table('sale_items')->count())->toBeGreaterThanOrEqual($db->table('sales')->count())
            ->and($db->table('ecommerce_orders')->whereNotNull('sale_id')->count())->toBeGreaterThan(0)
            ->and($db->table('abandoned_carts')->whereNotNull('sale_id')->count())->toBeGreaterThan(0)
            ->and($db->table('sales')->where('payment_method', 'credit')->count())->toBeGreaterThan(0)
            ->and($db->table('sale_payments')->count())->toBeGreaterThan(0)
            ->and($db->table('returns')->count())->toBeGreaterThan(0)
            ->and($db->table('refunds')->count())->toBeGreaterThan(0);

        // Customers in several shops, credit, expenses and the shared lists.
        expect($db->table('customers')->distinct()->count('shop_id'))->toBe(5)
            ->and($db->table('credit_accounts')->count())->toBeGreaterThan(0)
            ->and($db->table('credit_transactions')->count())->toBeGreaterThan(0)
            ->and($db->table('expenses')->count())->toBeGreaterThan(0)
            ->and($db->table('categories')->count())->toBeGreaterThan(0)
            ->and($db->table('attributes')->count())->toBeGreaterThan(0)
            ->and($db->table('suppliers')->count())->toBeGreaterThan(0);

        // Soft-deleted rows, and duplicate-looking records across shops.
        foreach (['products', 'customers', 'suppliers', 'categories', 'shops', 'expenses', 'ecommerce_orders', 'abandoned_carts', 'purchase_orders', 'stock_intakes'] as $table) {
            expect($db->table($table)->whereNotNull('deleted_at')->count())->toBeGreaterThan(0, "{$table} has no soft-deleted rows");
        }
        expect($db->table('customers')->select('phone')->groupBy('phone')->havingRaw('count(distinct shop_id) > 1')->get())->not->toBeEmpty()
            ->and($db->table('suppliers')->where('name', 'like', 'acme%')->count())->toBe(2);
    });
});

describe('refusals', function () {
    it('refuses to build without naming a target', function () {
        $this->artisan('dataset:build')
            ->expectsOutputToContain('name the target with --database=')
            ->assertFailed();
    });

    it('refuses a database that isn\'t a test database', function (string $connection, string $database) {
        config(["database.connections.{$connection}" => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'database' => $database, 'username' => 'nobody', 'password' => '',
        ]]);

        $this->artisan('dataset:build', ['--database' => $connection])
            ->expectsOutputToContain("isn't a test database")
            ->assertFailed();
    })->with([
        'the development connection' => ['mysql', 'pos-saas'],
        'a _testing connection pointing at a live database' => ['sneaky_testing', 'pos-saas'],
        'a live connection pointing at a test database' => ['sneaky', 'pos_saas_dataset_testing'],
    ]);

    it('refuses outside local and testing', function () {
        app()['env'] = 'production';

        $this->artisan('dataset:build', ['--database' => 'dataset_testing'])
            ->expectsOutputToContain('only built in the local or testing environment')
            ->assertFailed();
    });

    it('refuses an unknown connection', function () {
        $this->artisan('dataset:build', ['--database' => 'nothing_testing'])
            ->expectsOutputToContain("there is no 'nothing_testing' connection")
            ->assertFailed();
    });

    it('refuses a scale outside 0 to 5', function (string $scale) {
        $this->artisan('dataset:build', ['--database' => 'dataset_testing', '--scale' => $scale])
            ->expectsOutputToContain('--scale must be')
            ->assertFailed();
    })->with(['0', '-1', '6']);

    it('refuses a database that already has tables', function () {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Needs MySQL: runs with phpunit.mysql.xml.');
        }

        Schema::connection('dataset_testing')->create('already_here', fn ($table) => $table->id());

        try {
            $this->artisan('dataset:build', ['--database' => 'dataset_testing'])
                ->expectsOutputToContain("isn't empty")
                ->assertFailed();
        } finally {
            emptyDatasetDatabase();
        }
    });

    it('refuses to seed outside local and testing', function () {
        app()['env'] = 'production';

        expect(fn () => app(LiveLikeDatasetSeeder::class)->run())
            ->toThrow(RuntimeException::class, 'only built in the local or testing environment');
    });
});
