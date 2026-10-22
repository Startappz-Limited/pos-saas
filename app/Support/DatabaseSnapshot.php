<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Snapshots of a MySQL/MariaDB database, so a migration or import can be
 * rehearsed and rolled back (stage 0, live-data rule).
 *
 * Written in PHP over the Laravel connection rather than by shelling out to
 * mysqldump: lerd's PHP container ships MariaDB 11.8 clients while CI and the
 * local test server run MySQL 8, and the same PDO code works against both.
 *
 * Format: a gzipped SQL file. The first line is a JSON header (database,
 * time, row count per table); every statement is followed by the
 * STATEMENT_END line, so values containing ";" or newlines can't split one.
 */
class DatabaseSnapshot
{
    public const STATEMENT_END = '-- @@snapshot-statement-end@@';

    private const BATCH = 500;

    /**
     * Writes a snapshot of the connection's database. Read-only.
     *
     * @return array<string, int> row count per table
     */
    public function dump(string $connection, string $path): array
    {
        $db = $this->mysqlConnection($connection);
        $tables = $this->baseTables($db);
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = (int) $db->table($table)->count();
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $file = gzopen($path, 'wb6');

        if ($file === false) {
            throw new RuntimeException("Cannot write snapshot to {$path}");
        }

        try {
            gzwrite($file, '-- '.json_encode([
                'database' => $db->getDatabaseName(),
                'created_at' => now()->toIso8601String(),
                'tables' => $counts,
            ])."\n");

            $this->writeStatement($file, 'SET FOREIGN_KEY_CHECKS=0');

            foreach ($tables as $table) {
                $create = array_values((array) $db->selectOne("SHOW CREATE TABLE `{$table}`"))[1];
                $this->writeStatement($file, "DROP TABLE IF EXISTS `{$table}`");
                $this->writeStatement($file, $create);
                $this->writeRows($db, $file, $table);
            }

            $this->writeStatement($file, 'SET FOREIGN_KEY_CHECKS=1');
        } finally {
            gzclose($file);
        }

        return $counts;
    }

    /**
     * Replaces every table of the connection's database with the snapshot,
     * then checks each table's row count against the snapshot header.
     */
    public function restore(string $connection, string $path): void
    {
        $db = $this->mysqlConnection($connection);
        $header = $this->header($path);

        $db->statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->baseTables($db) as $table) {
            $db->statement("DROP TABLE IF EXISTS `{$table}`");
        }

        foreach ($this->statements($path) as $statement) {
            $db->unprepared($statement);
        }

        $db->statement('SET FOREIGN_KEY_CHECKS=1');

        foreach ($header['tables'] as $table => $expected) {
            $actual = (int) $db->table($table)->count();

            if ($actual !== $expected) {
                throw new RuntimeException("Restore check failed: {$table} has {$actual} rows, the snapshot has {$expected}.");
            }
        }
    }

    /**
     * @return array{database: string, created_at: string, tables: array<string, int>}
     */
    public function header(string $path): array
    {
        $file = $this->open($path);
        $line = gzgets($file);
        gzclose($file);

        $header = is_string($line) ? json_decode(substr(trim($line), 3), true) : null;

        if (! is_array($header) || ! isset($header['tables'])) {
            throw new RuntimeException("{$path} is not a database snapshot.");
        }

        return $header;
    }

    /**
     * CHECKSUM TABLE for every table, to compare a database before and after.
     *
     * @return array<string, string|null>
     */
    public function checksums(string $connection): array
    {
        $db = $this->mysqlConnection($connection);
        $sums = [];

        foreach ($this->baseTables($db) as $table) {
            $sums[$table] = (string) (array_values((array) $db->selectOne("CHECKSUM TABLE `{$table}`"))[1] ?? '');
        }

        return $sums;
    }

    private function mysqlConnection(string $connection): Connection
    {
        $db = DB::connection($connection);

        if (! in_array($db->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Snapshots support MySQL and MariaDB only; '{$connection}' uses {$db->getDriverName()}.");
        }

        return $db;
    }

    /**
     * @return list<string>
     */
    private function baseTables(Connection $db): array
    {
        return collect($db->select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']))
            ->map(fn (object $row): string => (string) array_values((array) $row)[0])
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  resource  $file
     */
    private function writeRows(Connection $db, $file, string $table): void
    {
        $pdo = $db->getPdo();

        // Generated columns are left out: the database computes them, and
        // refuses a value for one on restore (found in Phase 5 on
        // attributes.alive).
        $stored = collect($db->getSchemaBuilder()->getColumns($table))
            ->filter(fn (array $column): bool => empty($column['generation']))
            ->pluck('name')
            ->all();

        // One pass over the table: paging with OFFSET could skip or repeat rows
        // in tables without a unique first column (pivots).
        $db->table($table)->select($stored)->cursor()
            ->map(fn (object $row): array => (array) $row)
            ->chunk(self::BATCH)
            ->each(function ($rows) use ($pdo, $file, $table): void {
                $columns = '`'.implode('`, `', array_keys($rows->first())).'`';
                $values = $rows->map(fn (array $row): string => '('.implode(', ', array_map(
                    fn (mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    $row,
                )).')');

                $this->writeStatement($file, "INSERT INTO `{$table}` ({$columns}) VALUES ".$values->implode(', '));
            });
    }

    /**
     * @param  resource  $file
     */
    private function writeStatement($file, string $sql): void
    {
        gzwrite($file, $sql.";\n".self::STATEMENT_END."\n");
    }

    /**
     * @return \Generator<int, string>
     */
    private function statements(string $path): \Generator
    {
        $file = $this->open($path);
        gzgets($file); // header
        $buffer = '';

        try {
            while (($line = gzgets($file)) !== false) {
                if (rtrim($line, "\r\n") === self::STATEMENT_END) {
                    yield rtrim($buffer);
                    $buffer = '';

                    continue;
                }

                $buffer .= $line;
            }
        } finally {
            gzclose($file);
        }
    }

    /**
     * @return resource
     */
    private function open(string $path)
    {
        if (! is_file($path)) {
            throw new RuntimeException("Snapshot {$path} does not exist.");
        }

        $file = gzopen($path, 'rb');

        if ($file === false) {
            throw new RuntimeException("Cannot read snapshot {$path}.");
        }

        return $file;
    }
}
