<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * A database's schema as plain, comparable data: every table's columns,
 * indexes and foreign keys, sorted so two schemas built in a different order
 * compare equal.
 *
 * Used to prove the consolidated migrations build the same schema as the
 * history they replaced, and by the legacy import to check its source.
 */
class SchemaSnapshot
{
    /**
     * Tables that belong to the migrator, not the application.
     */
    private const IGNORED_TABLES = ['migrations'];

    /**
     * @return array<string, array{columns: array<string, array<string, mixed>>, indexes: array<string, array<string, mixed>>, foreign_keys: array<string, array<string, mixed>>}>
     */
    public static function of(?string $connection = null): array
    {
        $schema = Schema::connection($connection);

        $snapshot = [];
        foreach (self::tables($connection) as $table) {
            $snapshot[$table] = [
                'columns' => self::columns($schema->getColumns($table)),
                'indexes' => self::indexes($schema->getIndexes($table)),
                'foreign_keys' => self::foreignKeys($schema->getForeignKeys($table)),
            ];
        }

        return $snapshot;
    }

    /**
     * The database's own tables, without the migrator's, sorted.
     *
     * @return list<string>
     */
    public static function tables(?string $connection = null): array
    {
        $schema = Schema::connection($connection);
        // Only this database's tables: a MySQL server also lists every other
        // schema the account can see.
        $database = $schema->getConnection()->getDriverName() === 'sqlite'
            ? 'main'
            : $schema->getConnection()->getDatabaseName();

        return collect($schema->getTableListing(schema: $database, schemaQualified: false))
            ->reject(fn (string $table): bool => in_array($table, self::IGNORED_TABLES, true))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Where two snapshots differ, one line per difference.
     *
     * @param  array<string, mixed>  $expected
     * @param  array<string, mixed>  $actual
     * @return list<string>
     */
    public static function differences(array $expected, array $actual, string $path = ''): array
    {
        $differences = [];

        foreach (array_unique([...array_keys($expected), ...array_keys($actual)]) as $key) {
            $where = ltrim("{$path}.{$key}", '.');

            if (! array_key_exists($key, $actual)) {
                $differences[] = "{$where}: missing";
            } elseif (! array_key_exists($key, $expected)) {
                $differences[] = "{$where}: unexpected";
            } elseif (is_array($expected[$key]) && is_array($actual[$key]) && ! array_is_list($expected[$key])) {
                $differences = [...$differences, ...self::differences($expected[$key], $actual[$key], $where)];
            } elseif ($expected[$key] !== $actual[$key]) {
                $differences[] = "{$where}: expected ".json_encode($expected[$key]).', got '.json_encode($actual[$key]);
            }
        }

        return $differences;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @return array<string, array<string, mixed>>
     */
    private static function columns(array $columns): array
    {
        return collect($columns)
            ->mapWithKeys(fn (array $column): array => [$column['name'] => [
                'type' => strtolower((string) $column['type']),
                'nullable' => (bool) $column['nullable'],
                'default' => $column['default'] === null ? null : (string) $column['default'],
                'auto_increment' => (bool) $column['auto_increment'],
                'generation' => $column['generation'],
            ]])
            ->sortKeys()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $indexes
     * @return array<string, array<string, mixed>>
     */
    private static function indexes(array $indexes): array
    {
        return collect($indexes)
            ->mapWithKeys(fn (array $index): array => [strtolower((string) $index['name']) => [
                'columns' => $index['columns'],
                'unique' => (bool) $index['unique'],
                'primary' => (bool) $index['primary'],
            ]])
            ->sortKeys()
            ->all();
    }

    /**
     * Keyed by column list: SQLite doesn't report foreign key names.
     *
     * @param  list<array<string, mixed>>  $foreignKeys
     * @return array<string, array<string, mixed>>
     */
    private static function foreignKeys(array $foreignKeys): array
    {
        return collect($foreignKeys)
            ->mapWithKeys(fn (array $key): array => [implode(',', $key['columns']) => [
                'foreign_table' => $key['foreign_table'],
                'foreign_columns' => $key['foreign_columns'],
                'on_update' => strtolower((string) $key['on_update']),
                'on_delete' => strtolower((string) $key['on_delete']),
            ]])
            ->sortKeys()
            ->all();
    }
}
