<?php

namespace App\Console\Commands;

use App\Support\ConnectionGuard;
use App\Support\Legacy\LegacyImport;
use Illuminate\Console\Command;
use Throwable;

/**
 * Carries a database on the pre-SaaS schema across into the SaaS baseline,
 * as its first business (stage 1a; stage 0's "every schema change carries
 * existing data across").
 *
 * The legacy database is only read. The target must be a freshly migrated
 * baseline: migrate an empty database first, then import into it, then point
 * the app at it. Snapshot the legacy database first (db:snapshot).
 */
class ImportLegacyCommand extends Command
{
    protected $signature = 'saas:import-legacy
                            {--from= : Connection of the legacy database (only read)}
                            {--database= : Connection of the freshly migrated SaaS database to import into}
                            {--business-name= : Name of the business the data becomes}
                            {--currency=KES : The business\'s currency}
                            {--timezone=Africa/Nairobi : The business\'s timezone}';

    protected $description = 'Import a pre-SaaS database into the SaaS baseline as its first business (reads the source only)';

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $into = (string) $this->option('database');
        $name = trim((string) $this->option('business-name'));

        foreach (['--from' => $from, '--database' => $into] as $option => $connection) {
            if ($connection === '' || config("database.connections.{$connection}") === null) {
                $this->error("Refusing: name an existing connection with {$option}=<connection>.");

                return self::FAILURE;
            }
        }
        if ($name === '') {
            $this->error('Refusing: name the business with --business-name=.');

            return self::FAILURE;
        }

        // Only the two databases are touched; the legacy one only read.
        ConnectionGuard::only([$from, $into]);

        try {
            $import = new LegacyImport($from, $into, $this->legacySchema($from));

            if ($refusal = $import->refusal()) {
                $this->error("Refusing: {$refusal}");

                return self::FAILURE;
            }

            $this->info("Importing {$from} into {$into} as \"{$name}\"…");
            $import->run($name, (string) $this->option('currency'), (string) $this->option('timezone'));
            $problems = $import->verify();
        } catch (Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach (array_slice($import->notes, 0, 10) as $note) {
            $this->line("  · {$note}");
        }
        if (count($import->notes) > 10) {
            $this->line('  · … and '.(count($import->notes) - 10).' more notes in the report.');
        }

        $report = storage_path('app/imports/import-'.now()->format('Ymd-His').'.json');
        @mkdir(dirname($report), 0755, true);
        file_put_contents($report, json_encode([
            'from' => $from,
            'into' => $into,
            'business_id' => $import->businessId(),
            'notes' => $import->notes,
            'problems' => $problems,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->error("  ✗ {$problem}");
            }
            $this->error(count($problems)." mismatch(es); report: {$report}");

            return self::FAILURE;
        }

        $this->info("Imported and verified: counts, money totals, credit balances, stock and references all match. Report: {$report}");

        return self::SUCCESS;
    }

    /**
     * The recorded legacy schema for the source's engine.
     *
     * @return array<string, array<string, mixed>>
     */
    private function legacySchema(string $connection): array
    {
        $engine = config("database.connections.{$connection}.driver") === 'sqlite' ? 'sqlite' : 'mysql';

        return json_decode((string) file_get_contents(database_path("legacy/schema-{$engine}.json")), true);
    }
}
