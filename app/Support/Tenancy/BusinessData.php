<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Every row of one business, found from the database itself (stage 3): rows
 * with its `business_id`, then, repeatedly, every row whose foreign key points
 * at a row already found (a shop's sales, a sale's items, an item's cost
 * allocations…), plus the logins that belong to no other business. Read by the
 * data export and by the closure's erasure, so a table added later is covered
 * without being listed here.
 *
 * Works on the query builder (no tenant scope applies): every query names the
 * business or rows already found to be its own.
 */
class BusinessData
{
    /**
     * Platform-wide or transient tables: never a business's rows. The closure
     * record names its business but is kept: it records the erasure.
     */
    public const SHARED_TABLES = [
        'migrations', 'permissions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'business_closures',
    ];

    /**
     * Rows that point at a business's records but belong to a login, handled
     * with the logins (and never exported).
     */
    public const LOGIN_TABLES = ['sessions', 'password_reset_tokens', 'personal_access_tokens'];

    private const CHUNK = 500;

    /** @var array<string, list<array{columns: list<string>, foreign_table: string, foreign_columns: list<string>}>>|null */
    private ?array $foreignKeys = null;

    /** @var list<string>|null */
    private ?array $tables = null;

    public function __construct(private int $businessId, private ?string $connectionName = null) {}

    public function connection(): Connection
    {
        return DB::connection($this->connectionName);
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    /**
     * The logins that belong to this business only (every membership they
     * have is here) and aren't the platform operator. Logins also in another
     * business stay: only their membership here goes.
     *
     * @return list<int>
     */
    public function ownedUserIds(): array
    {
        $db = $this->connection();

        return $db->table('business_user')
            ->where('business_id', $this->businessId)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('business_user as elsewhere')
                ->whereColumn('elsewhere.user_id', 'business_user.user_id')
                ->where('elsewhere.business_id', '!=', $this->businessId))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('users as operators')
                ->whereColumn('operators.id', 'business_user.user_id')
                ->where('operators.is_platform_operator', true))
            ->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * The ids of the business's rows in every table that has an `id`, the
     * business itself included and logins excluded.
     *
     * @return array<string, list<int|string>>
     */
    public function ownedIds(): array
    {
        // Its own logins are followed too (their dashboard widgets, alert
        // recipients…): nothing of another business can point at them.
        $owned = ['businesses' => [$this->businessId], 'users' => $this->ownedUserIds()];
        $withId = array_values(array_filter($this->tables(), fn (string $table): bool => $this->hasId($table)
            && ! in_array($table, ['businesses', 'users', ...self::LOGIN_TABLES, ...self::SHARED_TABLES], true)));

        do {
            $grew = false;

            foreach ($withId as $table) {
                // Ids as stored: some tables key by uuid.
                $found = $this->rowsOf($table, $owned)->pluck('id')->all();
                $new = array_values(array_diff($found, $owned[$table] ?? []));

                if ($new !== []) {
                    $owned[$table] = [...($owned[$table] ?? []), ...$new];
                    $grew = true;
                }
            }
        } while ($grew);

        unset($owned['users']);

        return $owned;
    }

    /**
     * The business's rows in $table: with its `business_id`, or pointing at a
     * row already found (its own logins included).
     *
     * @param  array<string, list<int|string>>  $owned
     */
    public function rowsOf(string $table, array $owned): Builder
    {
        $db = $this->connection();
        $query = $db->table($table);
        $conditions = 0;

        $query->where(function ($where) use ($table, $owned, &$conditions): void {
            if (Schema::connection($this->connectionName)->hasColumn($table, 'business_id')) {
                $where->orWhere("{$table}.business_id", $this->businessId);
                $conditions++;
            }

            foreach ($this->foreignKeys()[$table] ?? [] as $key) {
                if (count($key['columns']) !== 1 || empty($owned[$key['foreign_table']])) {
                    continue;
                }

                $column = $key['columns'][0];
                foreach (array_chunk($owned[$key['foreign_table']], self::CHUNK) as $ids) {
                    $where->orWhereIn("{$table}.{$column}", $ids);
                }
                $conditions++;
            }
        });

        // Nothing ties this table to a business: no rows of its own.
        return $conditions === 0 ? $query->whereRaw('1 = 0') : $query;
    }

    /**
     * Tables with rows that belong to a business: everything but the shared
     * tables, with children before the tables their foreign keys point at.
     *
     * @return list<string>
     */
    public function tablesChildrenFirst(): array
    {
        $tables = array_values(array_diff($this->tables(), self::SHARED_TABLES));
        $parents = [];
        foreach ($tables as $table) {
            $parents[$table] = array_values(array_unique(array_filter(
                array_map(fn (array $key): string => $key['foreign_table'], $this->foreignKeys()[$table] ?? []),
                fn (string $parent): bool => $parent !== $table && in_array($parent, $tables, true),
            )));
        }

        // Repeatedly take the tables nothing remaining points at.
        $ordered = [];
        while ($parents !== []) {
            $pointedAt = array_merge(...array_values($parents));
            $leaves = array_values(array_filter(array_keys($parents), fn (string $table): bool => ! in_array($table, $pointedAt, true)));

            if ($leaves === []) {
                throw new RuntimeException('Foreign keys form a cycle among: '.implode(', ', array_keys($parents)));
            }

            foreach ($leaves as $leaf) {
                $ordered[] = $leaf;
                unset($parents[$leaf]);
            }
        }

        return $ordered;
    }

    /**
     * The foreign keys of $table that point at $table itself (a category's
     * parent category).
     *
     * @return list<string>
     */
    public function selfReferences(string $table): array
    {
        return array_values(array_map(
            fn (array $key): string => $key['columns'][0],
            array_filter($this->foreignKeys()[$table] ?? [], fn (array $key): bool => $key['foreign_table'] === $table && count($key['columns']) === 1),
        ));
    }

    public function hasId(string $table): bool
    {
        return Schema::connection($this->connectionName)->hasColumn($table, 'id');
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        if ($this->tables !== null) {
            return $this->tables;
        }

        $db = $this->connection();
        $schema = $db->getDriverName() === 'sqlite' ? 'main' : $db->getDatabaseName();

        return $this->tables = array_values(array_filter(
            Schema::connection($this->connectionName)->getTableListing($schema, false),
            fn (string $table): bool => ! str_starts_with($table, 'sqlite_'),
        ));
    }

    /**
     * @return array<string, list<array{columns: list<string>, foreign_table: string, foreign_columns: list<string>}>>
     */
    private function foreignKeys(): array
    {
        if ($this->foreignKeys !== null) {
            return $this->foreignKeys;
        }

        $keys = [];
        foreach ($this->tables() as $table) {
            foreach (Schema::connection($this->connectionName)->getForeignKeys($table) as $key) {
                $keys[$table][] = [
                    'columns' => array_values($key['columns']),
                    'foreign_table' => str_contains($key['foreign_table'], '.') ? substr(strrchr($key['foreign_table'], '.'), 1) : $key['foreign_table'],
                    'foreign_columns' => array_values($key['foreign_columns']),
                ];
            }
        }

        return $this->foreignKeys = $keys;
    }
}
