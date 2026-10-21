<?php

namespace App\Support\Database;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * A blank optional form field means "use the default". Laravel turns an empty
 * field into null and Eloquent sends it explicitly, which overrides the
 * column's default, so a column that can't be null (a shop's country, a
 * product's unit, an amount that defaults to 0) made the database refuse the
 * row. Before a model is saved, a null in such a column becomes the column's
 * default.
 *
 * Only ever replaces a null the database would reject: a column that accepts
 * null, has no default, or has an expression for a default (a timestamp) is
 * left alone, so a missing required value still fails loudly.
 */
final class ColumnDefaults
{
    /** @var array<string, array<string, string>> literal defaults by connection and table */
    private static array $cache = [];

    public static function apply(Model $model): void
    {
        $nulls = array_keys(array_filter($model->getDirty(), fn ($value): bool => $value === null));

        if ($nulls === []) {
            return;
        }

        $defaults = self::for($model);

        foreach ($nulls as $column) {
            if (array_key_exists($column, $defaults)) {
                $model->setAttribute($column, $defaults[$column]);
            }
        }
    }

    /**
     * The literal defaults of $model's columns that can't be null.
     *
     * @return array<string, string>
     */
    public static function for(Model $model): array
    {
        $connection = $model->getConnectionName() ?? config('database.default');
        $key = $connection.'.'.$model->getTable();

        if (! isset(self::$cache[$key])) {
            self::$cache[$key] = [];

            foreach (Schema::connection($connection)->getColumns($model->getTable()) as $column) {
                if ($column['nullable'] || $column['default'] === null || ! empty($column['generation'])) {
                    continue;
                }

                $literal = self::literal((string) $column['default']);

                if ($literal !== null) {
                    self::$cache[$key][$column['name']] = $literal;
                }
            }
        }

        return self::$cache[$key];
    }

    /**
     * A default as the value it stands for, or null for an expression
     * (CURRENT_TIMESTAMP, uuid(), …) that only the database can work out.
     * Engines report defaults differently: MySQL 8 unquoted ("Kenya", "0"),
     * MariaDB and SQLite quoted ("'Kenya'").
     */
    public static function literal(string $default): ?string
    {
        $default = trim($default);

        if (preg_match("/^'(.*)'$/s", $default, $quoted)) {
            return str_replace("''", "'", $quoted[1]);
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $default)) {
            return $default;
        }

        if (preg_match('/^(true|false)$/i', $default)) {
            return strtolower($default) === 'true' ? '1' : '0';
        }

        // An expression, or MariaDB's NULL: nothing to fill in.
        if (str_contains($default, '(') || preg_match('/^(null|current_timestamp|now|localtimestamp|current_date|current_time)$/i', $default)) {
            return null;
        }

        // MySQL 8 reports a string default without quotes.
        return $default;
    }

    /**
     * Forgets what was read (after a migration in the same process).
     */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
