<?php

use App\Support\Legacy\LegacyImport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The one-off import of a pre-SaaS database into the SaaS baseline
 * (saas:import-legacy, stage 1a).
 *
 * Both databases are SQLite in memory: the source is built from the baseline
 * (a superset of the legacy schema) and holds rows as the legacy schema has
 * them; the importer reads only legacy columns. The import of real legacy
 * data (main's live-like dataset, the old development database) is rehearsed
 * on MySQL in CI and locally.
 */
beforeEach(function () {
    foreach (['legacy_testing', 'import_testing'] as $connection) {
        config(["database.connections.{$connection}" => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::purge($connection);
        $default = DB::getDefaultConnection();
        try {
            Artisan::call('migrate', ['--database' => $connection, '--force' => true]);
        } finally {
            DB::setDefaultConnection($default);
        }
    }

    // The legacy key: one credit account per customer and shop.
    Schema::connection('legacy_testing')->table('credit_accounts', function ($table): void {
        $table->dropUnique(['customer_id']);
        $table->unique(['customer_id', 'shop_id']);
    });

    $this->legacy = DB::connection('legacy_testing');
    seedLegacyData($this->legacy);
});

/**
 * Inserts a legacy row, adding a uuid and the source's (ignored) business.
 */
function legacyRow(string $table, array $row): int
{
    $db = DB::connection('legacy_testing');
    $columns = Schema::connection('legacy_testing')->getColumnListing($table);
    $row += array_filter([
        'uuid' => in_array('uuid', $columns, true) ? (string) Str::uuid() : null,
        'business_id' => in_array('business_id', $columns, true) && $table !== 'roles' ? 1 : null,
        'created_at' => in_array('created_at', $columns, true) ? now() : null,
        'updated_at' => in_array('updated_at', $columns, true) ? now() : null,
    ], fn ($value) => $value !== null);

    $db->table($table)->insert($row);

    return (int) ($row['id'] ?? $db->getPdo()->lastInsertId());
}

function seedLegacyData($legacy): void
{
    // The source is built from the baseline, so its NOT NULL business_id
    // needs a row; the import ignores it.
    $legacy->table('businesses')->insert(['id' => 1, 'uuid' => (string) Str::uuid(), 'name' => 'ignored', 'created_at' => now(), 'updated_at' => now()]);

    legacyRow('users', ['id' => 10, 'name' => 'Owner', 'email' => 'owner@legacy.test', 'password' => 'x', 'status' => 'active']);
    legacyRow('users', ['id' => 11, 'name' => 'Former Cashier', 'email' => 'former@legacy.test', 'password' => 'x', 'status' => 'inactive']);
    legacyRow('roles', ['id' => 1, 'name' => 'super-admin', 'guard_name' => 'web']);
    legacyRow('model_has_roles', ['role_id' => 1, 'model_type' => 'App\\Models\\User', 'model_id' => 10]);

    legacyRow('shops', ['id' => 20, 'name' => 'Main', 'code' => 'MAIN']);
    legacyRow('shops', ['id' => 21, 'name' => 'Branch', 'code' => 'BRANCH']);
    legacyRow('shop_user', ['shop_id' => 21, 'user_id' => 11]);
    legacyRow('categories', ['id' => 30, 'name' => 'Supplements', 'slug' => 'supplements']);

    // P1: its own shop. P2: no shop of its own, listed in the branch with an
    // e-commerce stock figure that differs. P3: a variation with stock.
    legacyRow('products', ['id' => 40, 'name' => 'Whey', 'slug' => 'whey', 'sku' => 'WHEY', 'category_id' => 30, 'shop_id' => 20, 'stock_quantity' => 10, 'selling_price' => 3500]);
    legacyRow('products', ['id' => 41, 'name' => 'Creatine', 'slug' => 'creatine', 'sku' => 'CREA', 'category_id' => 30, 'shop_id' => null, 'stock_quantity' => 6, 'selling_price' => 2500]);
    legacyRow('products', ['id' => 42, 'name' => 'Shaker', 'slug' => 'shaker', 'sku' => 'SHAK', 'category_id' => 30, 'shop_id' => 20, 'stock_quantity' => 0, 'has_variations' => true]);
    legacyRow('product_variations', ['id' => 50, 'product_id' => 42, 'name' => 'Black', 'sku' => 'SHAK-BLK', 'stock_quantity' => 5]);
    legacyRow('shop_product', ['shop_id' => 20, 'product_id' => 40, 'stock_quantity' => 10]);
    legacyRow('shop_product', ['shop_id' => 21, 'product_id' => 41, 'stock_quantity' => 4]);

    // Customer 60 has an account in each shop; 61 has one.
    legacyRow('customers', ['id' => 60, 'shop_id' => 20, 'code' => 'C-60', 'name' => 'Gym Ltd', 'customer_type' => 'wholesale']);
    legacyRow('customers', ['id' => 61, 'shop_id' => 21, 'code' => 'C-61', 'name' => 'Club Ltd', 'customer_type' => 'wholesale']);
    legacyRow('credit_accounts', ['id' => 70, 'customer_id' => 60, 'shop_id' => 20, 'status' => 'active', 'credit_limit' => 500, 'current_balance' => 100]);
    legacyRow('credit_accounts', ['id' => 71, 'customer_id' => 60, 'shop_id' => 21, 'status' => 'active', 'credit_limit' => 800, 'current_balance' => 50]);
    legacyRow('credit_accounts', ['id' => 72, 'customer_id' => 61, 'shop_id' => 21, 'status' => 'active', 'credit_limit' => 300, 'current_balance' => 0]);
    legacyRow('credit_transactions', ['id' => 80, 'credit_account_id' => 71, 'customer_id' => 60, 'shop_id' => 21, 'transaction_number' => 'CT-80', 'type' => 'sale', 'debit' => 50, 'balance_before' => 0, 'balance_after' => 50]);

    legacyRow('sale_sources', ['id' => 1, 'name' => 'Walk-in']);
    legacyRow('cash_registers', ['id' => 90, 'shop_id' => 20, 'user_id' => 10, 'register_number' => 'REG-90', 'register_date' => today(), 'opened_at' => now()]);
    legacyRow('sales', ['id' => 100, 'shop_id' => 20, 'register_id' => 90, 'invoice_number' => 'INV-100', 'status' => 'completed', 'payment_status' => 'paid', 'sale_type' => 'regular', 'total_amount' => 3500]);
    legacyRow('sale_payments', ['id' => 110, 'sale_id' => 100, 'payment_number' => 'PAY-110', 'payment_method' => 'cash', 'amount' => 3500, 'paid_at' => now()]);
}

function legacySchemaFor(): array
{
    return json_decode(file_get_contents(database_path('legacy/schema-sqlite.json')), true);
}

function runLegacyImport(): LegacyImport
{
    $import = new LegacyImport('legacy_testing', 'import_testing', legacySchemaFor());
    $import->run('Legacy Retail');

    return $import;
}

it('imports every legacy row into one business and verifies it', function () {
    $import = runLegacyImport();
    $target = DB::connection('import_testing');
    $businessId = $import->businessId();

    expect($import->verify())->toBe([])
        ->and($target->table('businesses')->pluck('name')->all())->toBe(['Legacy Retail'])
        // Ids are kept, and everything joins the business.
        ->and($target->table('shops')->orderBy('id')->pluck('id')->all())->toBe([20, 21])
        ->and($target->table('shops')->distinct()->pluck('business_id')->all())->toBe([$businessId])
        ->and($target->table('sales')->where('id', 100)->value('business_id'))->toBe($businessId)
        ->and($target->table('sale_payments')->where('id', 110)->value('business_id'))->toBe($businessId);
});

it('makes every user a member, inactive users inactive, with grants held in the business', function () {
    $businessId = runLegacyImport()->businessId();
    $target = DB::connection('import_testing');

    expect($target->table('business_user')->orderBy('user_id')->pluck('status', 'user_id')->all())->toBe([10 => 'active', 11 => 'inactive'])
        ->and($target->table('users')->distinct()->pluck('default_business_id')->all())->toBe([$businessId])
        ->and($target->table('model_has_roles')->value('business_id'))->toBe($businessId)
        // Roles stay shared templates.
        ->and($target->table('roles')->value('business_id'))->toBeNull();
});

it('merges a customer\'s credit accounts into the oldest, keeping every amount', function () {
    $import = runLegacyImport();
    $target = DB::connection('import_testing');
    $account = $target->table('credit_accounts')->where('customer_id', 60)->sole();

    expect($account->id)->toBe(70)
        ->and((float) $account->current_balance)->toBe(150.0)
        ->and((float) $account->credit_limit)->toBe(800.0)
        ->and((float) $account->available_credit)->toBe(650.0)
        ->and($target->table('credit_transactions')->where('id', 80)->value('credit_account_id'))->toBe(70)
        ->and($target->table('credit_accounts')->count())->toBe(2)
        ->and($import->notes)->toContain('Customer 60: 2 credit accounts merged into #70 (balance 150.00, limit 800.00).');
});

it('gives every product a default variation and places its stock in its own shop', function () {
    $import = runLegacyImport();
    $target = DB::connection('import_testing');
    $stock = fn (int $product) => $target->table('shop_variation')->where('shop_variation.product_id', $product)
        ->join('product_variations', 'product_variations.id', '=', 'shop_variation.variation_id')
        ->orderBy('shop_variation.shop_id')->orderBy('shop_variation.variation_id')
        ->get(['shop_variation.shop_id', 'product_variations.is_default', 'shop_variation.stock_quantity'])
        ->map(fn ($row) => [(int) $row->shop_id, (bool) $row->is_default, (int) $row->stock_quantity])->all();

    expect($target->table('product_variations')->where('is_default', true)->count())->toBe(3)
        ->and($stock(40))->toBe([[20, true, 10]])
        // No shop of its own: its first listed shop holds it, which is noted.
        ->and($stock(41))->toBe([[21, true, 6]])
        ->and($stock(42))->toBe([[20, false, 5], [20, true, 0]])
        // The legacy columns are kept as they were.
        ->and($target->table('products')->where('id', 41)->value('stock_quantity'))->toBe(6)
        ->and(collect($import->notes)->filter(fn ($note) => str_starts_with($note, 'Product 41:'))->values()->all())->toBe([
            'Product 41: stock placed in shop 21, its first listed shop.',
            'Product 41: shop 21 listed 4 in stock (e-commerce figure); the POS figure 6 is used.',
        ]);
});

it('only reads the legacy database', function () {
    $before = DB::connection('legacy_testing')->table('credit_accounts')->count();
    runLegacyImport();

    expect(DB::connection('legacy_testing')->table('credit_accounts')->count())->toBe($before)
        ->and(fn () => DB::connection('legacy_testing')->table('shops')->delete())
        ->toThrow(RuntimeException::class, 'read-only');
});

it('refuses a target that already holds data', function () {
    runLegacyImport();

    expect((new LegacyImport('legacy_testing', 'import_testing', legacySchemaFor()))->refusal())
        ->toContain('already holds data');
});

it('refuses a source missing part of the legacy schema', function () {
    $schema = legacySchemaFor();
    $schema['shops']['columns']['legacy_only_column'] = ['type' => 'varchar'];

    expect((new LegacyImport('legacy_testing', 'import_testing', $schema))->refusal())
        ->toContain('shops.legacy_only_column');
});

it('reports a mismatch when the target loses rows', function () {
    $import = runLegacyImport();
    DB::connection('import_testing')->table('sale_payments')->delete();

    expect($import->verify())->toContain('sale_payments: 1 rows in the source, 0 imported');
});
