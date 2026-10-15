<?php

namespace App\Support\Legacy;

use App\Support\SchemaSnapshot;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The one-off import of a database on the pre-SaaS schema into the SaaS
 * baseline, as its first business (stage 0's "work as though live" rule and
 * decision 4.7).
 *
 * The baseline keeps every legacy column (MigrationBaselineTest), so each
 * legacy row is copied as it is, with its id and uuid, plus what the SaaS
 * schema adds:
 *
 * - one business, which every shop, product, list, customer and sale joins;
 * - a membership for every user (inactive users get inactive ones), with the
 *   business as their default; role and permission grants held in it;
 * - one credit account per customer: several accounts are merged into the
 *   oldest (balances summed, the highest limit kept, transactions pointed at
 *   it), each merge reported;
 * - a default variation for every product, and per-shop stock rows: a
 *   product's stock goes to its own shop (products.shop_id, else its first
 *   listed shop, else the oldest shop), each placement by a fallback reported.
 *
 * The source is only read. The target must hold the baseline and nothing
 * else. verify() compares the two and lists every mismatch.
 */
class LegacyImport
{
    /** Transient framework tables: not carried across. */
    private const SKIPPED_TABLES = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    /** Tables whose rows are rebuilt rather than copied. */
    private const REBUILT_TABLES = ['credit_accounts'];

    /**
     * Legacy tables no legacy code ever wrote, rebuilt in the SaaS schema
     * (FIFO costing, stage 1b): not carried, and refused if they hold rows.
     */
    private const NEVER_WRITTEN_TABLES = ['cost_layers', 'cost_allocations'];

    private const CHUNK = 500;

    private Connection $source;

    private Connection $target;

    private ?int $businessId = null;

    /** @var list<string> */
    public array $notes = [];

    /** @var array<int, int> merged credit account id => kept id */
    private array $accountMap = [];

    /**
     * @param  array<string, array<string, mixed>>  $legacySchema  database/legacy/schema-{mysql,sqlite}.json
     */
    public function __construct(
        string $from,
        string $into,
        private array $legacySchema,
    ) {
        if ($from === $into) {
            throw new RuntimeException('The source and the target must be different connections.');
        }

        $this->source = DB::connection($from);
        $this->target = DB::connection($into);
        $this->readOnly($this->source);
    }

    /**
     * Why the import can't run, or null when it can.
     */
    public function refusal(): ?string
    {
        $tables = SchemaSnapshot::tables($this->target->getName());

        if (! in_array('businesses', $tables, true)) {
            return "The target ({$this->target->getDatabaseName()}) doesn't hold the SaaS baseline: migrate it first.";
        }

        foreach (['businesses', 'users', 'shops', 'products', 'sales'] as $table) {
            if ($this->target->table($table)->exists()) {
                return "The target ({$this->target->getDatabaseName()}) already holds data ({$table}): import only into a freshly migrated database.";
            }
        }

        $missing = [];
        $sourceTables = SchemaSnapshot::tables($this->source->getName());
        foreach ($this->legacySchema as $table => $definition) {
            if (in_array($table, self::SKIPPED_TABLES, true)) {
                continue;
            }
            if (in_array($table, self::NEVER_WRITTEN_TABLES, true)) {
                if (in_array($table, $sourceTables, true) && $this->source->table($table)->exists()) {
                    return "The source's {$table} holds rows; no legacy code wrote that table, so they can't be carried across.";
                }

                continue;
            }
            if (! in_array($table, $sourceTables, true)) {
                $missing[] = $table;

                continue;
            }
            $columns = $this->source->getSchemaBuilder()->getColumnListing($table);
            foreach (array_keys($definition['columns']) as $column) {
                if (! in_array($column, $columns, true)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        return $missing === [] ? null
            : 'The source is missing parts of the legacy schema: '.implode(', ', array_slice($missing, 0, 10)).(count($missing) > 10 ? ', …' : '');
    }

    public function run(string $businessName, string $currency = 'KES', string $timezone = 'Africa/Nairobi'): void
    {
        if ($refusal = $this->refusal()) {
            throw new RuntimeException($refusal);
        }

        $this->noteExtraColumns();
        $this->target->getSchemaBuilder()->disableForeignKeyConstraints();

        try {
            $this->target->transaction(function () use ($businessName, $currency, $timezone): void {
                $this->businessId = $this->target->table('businesses')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'name' => $businessName,
                    'status' => 'active',
                    'currency' => $currency,
                    'timezone' => $timezone,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->mergeCreditAccounts();

                foreach ($this->copiedTables() as $table) {
                    $this->copy($table);
                }

                $this->memberships();
                $this->defaultVariationsAndStock();
            });
        } finally {
            $this->target->getSchemaBuilder()->enableForeignKeyConstraints();
        }
    }

    public function businessId(): ?int
    {
        return $this->businessId;
    }

    /**
     * Every mismatch between source and target; empty when the import is
     * faithful.
     *
     * @return list<string>
     */
    public function verify(): array
    {
        $problems = [];

        foreach ($this->copiedTables() as $table) {
            $from = $this->source->table($table)->count();
            $to = $this->target->table($table)->count();
            if ($table === 'product_variations') {
                $to -= $this->target->table($table)->where('is_default', true)->count();
            }
            if ($from !== $to) {
                $problems[] = "{$table}: {$from} rows in the source, {$to} imported";
            }
        }

        $merged = count($this->accountMap);
        $accounts = $this->source->table('credit_accounts')->count() - $merged;
        if ($this->target->table('credit_accounts')->count() !== $accounts) {
            $problems[] = "credit_accounts: expected {$accounts} after {$merged} merges, found ".$this->target->table('credit_accounts')->count();
        }

        foreach (['sales.total_amount', 'sale_payments.amount', 'expenses.amount', 'credit_transactions.debit', 'credit_transactions.credit', 'credit_accounts.current_balance'] as $sum) {
            [$table, $column] = explode('.', $sum);
            $from = round((float) $this->source->table($table)->sum($column), 2);
            $to = round((float) $this->target->table($table)->sum($column), 2);
            if ($from !== $to) {
                $problems[] = "{$sum}: {$from} in the source, {$to} imported";
            }
        }

        $legacyVariationStock = (int) $this->source->table('product_variations')->sum('stock_quantity');
        $legacyStock = (int) $this->source->table('products')->sum('stock_quantity') + $legacyVariationStock;
        $placedStock = (int) $this->target->table('shop_variation')->sum('stock_quantity');
        if ($legacyStock !== $placedStock) {
            $problems[] = "stock: {$legacyStock} units in the source, {$placedStock} placed in shops";
        }

        // FIFO layers add up to stock: layers hold what's above zero, and
        // provisional shortfalls cover what's below it.
        $layered = (int) $this->target->table('cost_layers')->sum('remaining_quantity')
            - (int) $this->target->table('cost_allocations')->where('is_provisional', true)->sum('quantity_allocated');
        if ($layered !== $placedStock) {
            $problems[] = "cost layers: {$layered} units, stock: {$placedStock}";
        }

        $balances = fn (Connection $db) => $db->table('credit_accounts')->groupBy('customer_id')
            ->selectRaw('customer_id, round(sum(current_balance), 2) as balance')->pluck('balance', 'customer_id')
            ->map(fn ($balance) => (float) $balance)->all();
        $diff = array_diff_assoc($balances($this->source), $balances($this->target));
        if ($diff !== []) {
            $problems[] = 'credit balance differs for '.count($diff).' customer(s), e.g. customer '.array_key_first($diff);
        }

        $withoutDefault = $this->target->table('products')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('product_variations')
                ->whereColumn('product_variations.product_id', 'products.id')->where('is_default', true))
            ->count();
        if ($withoutDefault > 0) {
            $problems[] = "{$withoutDefault} product(s) without a default variation";
        }

        return [...$problems, ...$this->orphans()];
    }

    // --- copying --------------------------------------------------------

    /**
     * @return list<string>
     */
    private function copiedTables(): array
    {
        return collect(array_keys($this->legacySchema))
            ->reject(fn (string $table): bool => in_array($table, [...self::SKIPPED_TABLES, ...self::REBUILT_TABLES, ...self::NEVER_WRITTEN_TABLES], true))
            ->values()
            ->all();
    }

    private function copy(string $table): void
    {
        $columns = array_values(array_intersect(
            array_keys($this->legacySchema[$table]['columns']),
            $this->target->getSchemaBuilder()->getColumnListing($table),
        ));
        $targetColumns = $this->target->getSchemaBuilder()->getColumnListing($table);
        $addBusiness = in_array('business_id', $targetColumns, true) && $table !== 'roles';
        $order = in_array('id', $columns, true) ? 'id' : $columns[0];

        $this->source->table($table)->select($columns)->orderBy($order)
            ->chunk(self::CHUNK, function (Collection $rows) use ($table, $addBusiness): void {
                $this->target->table($table)->insert($rows->map(function (object $row) use ($table, $addBusiness): array {
                    $row = (array) $row;
                    if ($addBusiness) {
                        $row['business_id'] = $this->businessId;
                    }
                    if ($table === 'users') {
                        $row['default_business_id'] = $this->businessId;
                    }
                    if (isset($row['credit_account_id'], $this->accountMap[$row['credit_account_id']])) {
                        $row['credit_account_id'] = $this->accountMap[$row['credit_account_id']];
                    }

                    return $row;
                })->all());
            });
    }

    /**
     * One account per customer: the oldest account (a live one if there is
     * one) is kept, with the balances summed and the highest limit.
     */
    private function mergeCreditAccounts(): void
    {
        $columns = array_values(array_intersect(
            array_keys($this->legacySchema['credit_accounts']['columns']),
            $this->target->getSchemaBuilder()->getColumnListing('credit_accounts'),
        ));

        $this->source->table('credit_accounts')->select($columns)->orderBy('id')->get()
            ->groupBy('customer_id')
            ->each(function (Collection $accounts): void {
                $kept = (array) ($accounts->first(fn (object $account) => $account->deleted_at === null) ?? $accounts->first());

                if ($accounts->count() > 1) {
                    $limit = (float) $accounts->max('credit_limit');
                    $balance = round((float) $accounts->sum('current_balance'), 2);
                    $kept['credit_limit'] = $limit;
                    $kept['current_balance'] = $balance;
                    $kept['available_credit'] = max(0, $limit - $balance);

                    foreach ($accounts as $account) {
                        if ($account->id !== $kept['id']) {
                            $this->accountMap[$account->id] = $kept['id'];
                        }
                    }
                    $this->notes[] = sprintf('Customer %d: %d credit accounts merged into #%d (balance %.2f, limit %.2f).', $kept['customer_id'], $accounts->count(), $kept['id'], $balance, $limit);
                }

                $kept['business_id'] = $this->businessId;
                $this->target->table('credit_accounts')->insert($kept);
            });
    }

    private function memberships(): void
    {
        $this->target->table('users')->orderBy('id')->select(['id', 'status', 'created_at'])
            ->chunk(self::CHUNK, function (Collection $users): void {
                $this->target->table('business_user')->insert($users->map(fn (object $user): array => [
                    'business_id' => $this->businessId,
                    'user_id' => $user->id,
                    'status' => $user->status === 'active' ? 'active' : 'inactive',
                    'joined_at' => $user->created_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });
    }

    /**
     * A default variation per product, and a stock row for every variation
     * in each of the product's shops. A product's stock (and its variations')
     * goes to the product's own shop; its other shops start at zero.
     */
    private function defaultVariationsAndStock(): void
    {
        $oldestShop = $this->target->table('shops')->orderBy('id')->value('id');
        // The legacy stock figures come from the source: the SaaS schema has
        // no stock columns on products and variations (stage 1b).
        $listed = $this->source->table('shop_product')->orderBy('shop_id')->get(['product_id', 'shop_id', 'stock_quantity'])->groupBy('product_id');
        $productStock = $this->source->table('products')->pluck('stock_quantity', 'id');
        $variationStock = $this->source->table('product_variations')->pluck('stock_quantity', 'id');

        $this->target->table('products')->orderBy('id')
            ->select(['id', 'uuid', 'shop_id', 'cost_price', 'selling_price', 'wholesale_price', 'tax_class', 'status'])
            ->chunk(self::CHUNK, function (Collection $products) use ($oldestShop, $listed, $productStock, $variationStock): void {
                foreach ($products as $product) {
                    $product->stock_quantity = (int) ($productStock[$product->id] ?? 0);
                    $defaultId = $this->target->table('product_variations')->insertGetId([
                        'business_id' => $this->businessId,
                        'uuid' => (string) Str::uuid(),
                        'product_id' => $product->id,
                        'name' => 'Default',
                        'sku' => 'DEFAULT-'.$product->uuid,
                        'cost_price' => $product->cost_price,
                        'selling_price' => $product->selling_price ?? 0,
                        'wholesale_price' => $product->wholesale_price,
                        'tax_class' => $product->tax_class,
                        'status' => $product->status ?? 'active',
                        'is_default' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $pivot = $listed->get($product->id, collect());
                    $ownShop = $product->shop_id ?? $pivot->first()?->shop_id ?? $oldestShop;
                    if ($ownShop === null) {
                        $this->notes[] = "Product {$product->id}: no shop to hold its stock (there are no shops).";

                        continue;
                    }
                    if ($product->shop_id === null) {
                        $this->notes[] = "Product {$product->id}: stock placed in shop {$ownShop}, ".($pivot->isEmpty() ? 'the oldest shop' : 'its first listed shop').'.';
                    }
                    foreach ($pivot as $row) {
                        if ((int) $row->stock_quantity !== 0 && ((int) $row->shop_id !== (int) $ownShop || (int) $row->stock_quantity !== (int) $product->stock_quantity)) {
                            $this->notes[] = "Product {$product->id}: shop {$row->shop_id} listed {$row->stock_quantity} in stock (e-commerce figure); the POS figure {$product->stock_quantity} is used.";
                        }
                    }

                    $shops = $pivot->pluck('shop_id')->push($ownShop)->map(fn ($id) => (int) $id)->unique();
                    $variations = $this->target->table('product_variations')->where('product_id', $product->id)->get(['id', 'cost_price']);
                    $rows = [];
                    foreach ($shops as $shopId) {
                        foreach ($variations as $variation) {
                            $stock = 0;
                            if ($shopId === (int) $ownShop) {
                                $stock = (int) $variation->id === $defaultId ? (int) $product->stock_quantity : (int) ($variationStock[$variation->id] ?? 0);
                                $this->openingCost($shopId, $product, $variation, $stock);
                            }
                            $rows[] = [
                                'shop_id' => $shopId,
                                'product_id' => $product->id,
                                'variation_id' => $variation->id,
                                'stock_quantity' => $stock,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                    $this->target->table('shop_variation')->insert($rows);
                }
            });
    }

    /**
     * FIFO cost for imported stock: an opening layer at the variation's cost
     * (a default variation's is its product's), so layers add up to stock. A
     * negative figure becomes a provisional shortfall the next delivery
     * settles, as a sale below zero would.
     */
    private function openingCost(int $shopId, object $product, object $variation, int $stock): void
    {
        if ($stock === 0) {
            return;
        }

        $cost = $variation->cost_price ?? $product->cost_price;
        $now = now();
        $common = [
            'uuid' => (string) Str::uuid(),
            'business_id' => $this->businessId,
            'shop_id' => $shopId,
            'product_id' => $product->id,
            'variation_id' => $variation->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($stock > 0) {
            $this->target->table('cost_layers')->insert([...$common,
                'source_type' => 'opening',
                'unit_cost' => $cost,
                'original_quantity' => $stock,
                'remaining_quantity' => $stock,
                'received_at' => $now,
            ]);

            return;
        }

        $this->target->table('cost_allocations')->insert([...$common,
            'consumer_type' => 'opening',
            'consumer_id' => $variation->id,
            'quantity_allocated' => -$stock,
            'unit_cost' => $cost,
            'total_cost' => $cost === null ? null : round(-$stock * (float) $cost, 2),
            'is_provisional' => true,
            'provisional_unit_cost' => $cost,
            'allocated_at' => $now,
        ]);
        $this->notes[] = "Product {$product->id}: stock {$stock} in shop {$shopId} imported as below zero; the next delivery settles it.";
    }

    // --- safety and checks ------------------------------------------------

    /**
     * The source is only ever read: anything but a read refuses to run.
     */
    private function readOnly(Connection $connection): void
    {
        $connection->beforeExecuting(function (string $query) use ($connection): void {
            if (! preg_match('/^\s*(select|show|describe|explain|with)\b/i', $query) && ! preg_match('/^\s*pragma\s+\w+\s*(\(|$)/i', $query)) {
                throw new RuntimeException("The legacy source ({$connection->getName()}) is read-only; refused: ".mb_substr($query, 0, 120));
            }
        });
    }

    private function noteExtraColumns(): void
    {
        foreach ($this->legacySchema as $table => $definition) {
            if (in_array($table, [...self::SKIPPED_TABLES, ...self::NEVER_WRITTEN_TABLES], true)) {
                continue;
            }
            $extra = array_diff($this->source->getSchemaBuilder()->getColumnListing($table), array_keys($definition['columns']));
            if ($extra !== []) {
                $this->notes[] = "{$table}: source columns not in the legacy schema, not imported: ".implode(', ', $extra).'.';
            }
        }
    }

    /**
     * Rows in the target pointing at a parent that doesn't exist.
     *
     * @return list<string>
     */
    private function orphans(): array
    {
        $problems = [];

        foreach (SchemaSnapshot::of($this->target->getName()) as $table => $definition) {
            foreach ($definition['foreign_keys'] as $columns => $key) {
                if (str_contains($columns, ',')) {
                    continue;
                }
                $orphans = $this->target->table($table)->whereNotNull($columns)
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')->from($key['foreign_table'])
                        ->whereColumn("{$key['foreign_table']}.{$key['foreign_columns'][0]}", "{$table}.{$columns}"))
                    ->count();
                if ($orphans > 0) {
                    $problems[] = "{$table}.{$columns}: {$orphans} row(s) point at a missing {$key['foreign_table']} row";
                }
            }
        }

        return $problems;
    }
}
