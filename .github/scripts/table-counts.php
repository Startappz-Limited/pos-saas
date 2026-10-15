<?php

/*
 * Prints the row count of every table on a connection as JSON, for the CI
 * rehearsal (the dataset must be unchanged after the legacy import reads it).
 *
 * Usage: php artisan tinker --execute="require '.github/scripts/table-counts.php';" CONNECTION
 * The connection comes from TABLE_COUNTS_CONNECTION (defaults to dataset_testing).
 */

use Illuminate\Support\Facades\DB;

$connection = getenv('TABLE_COUNTS_CONNECTION') ?: 'dataset_testing';
$db = DB::connection($connection);

$counts = collect($db->select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']))
    ->map(fn (object $row): string => (string) array_values((array) $row)[0])
    ->sort()
    ->mapWithKeys(fn (string $table): array => [$table => $db->table($table)->count()])
    ->all();

echo json_encode($counts, JSON_PRETTY_PRINT)."\n";
